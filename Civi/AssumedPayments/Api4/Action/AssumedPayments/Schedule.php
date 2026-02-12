<?php

declare(strict_types = 1);

namespace Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi;
use Civi\Api4\AssumedPayments;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use CRM_AssumedPayments_Queue_AssumedPaymentWorker;
use CRM_AssumedPayments_ExtensionUtil as E;

/**
 * Resolves the effective scheduling parameters (API overrides > settings),
 * determines relevant recurring contribution ids for the given window, and
 * enqueues one queue task per recurring contribution into the SQL queue
 * `assumed_payments`.
 */
class Schedule extends AbstractAction {

  public function __construct() {
    parent::__construct(AssumedPayments::getEntityName(), 'schedule');
  }

  // API overrides
  protected ?string $fromDate = NULL;
  protected ?string $toDate = NULL;
  protected ?int $batchSize = NULL;
  protected ?bool $dryRun = NULL;
  protected ?array $openStatusIds = NULL;

  /**
   * Schedules recurring contributions for assumed payment processing.
   *
   * @param \Civi\Api4\Generic\Result $result
   *
   * @throws \Civi\Core\Exception\DBQueryException
   * @throws \CRM_Core_Exception
   */
  public function _run(Result $result): void {
    $settings = Civi::settings();

    // Resolve date range: API override > settings
    $from = $this->fromDate ?? $settings->get('assumed_payments_from_date');
    $to = $this->toDate ?? $settings->get('assumed_payments_to_date');

    // Batch Size
    $batchSize = $this->batchSize ?? $settings->get('assumed_payments_batch_size');

    // Dry run
    $isDryRun = $this->dryRun;
    if ($isDryRun === NULL) {
      $isDryRun = $settings->get('assumed_payments_dry_run_default') ?? FALSE;
    }

    //Status Ids
    $openStatusIds = $this->openStatusIds;
    if ($openStatusIds === NULL) {
      $openStatusIds = $settings->get('assumed_payments_contribution_status_ids') ?? [];
    }
    $openStatusIds = array_values(
      array_map('intval', $openStatusIds)
    );

    // Find relevant recur IDs (DB-side) based on missing contribution in range OR open contribution status in range
    $ids = $this->findRelevantRecurIds($from, $to, $openStatusIds, $batchSize);

    // Create queue
    $queue = \CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => E::SHORT_NAME . '_schedule',
      'reset' => TRUE,
    ]);

    $queued = 0;
    foreach ($ids as $recurId) {
      $queue->createItem(
        new \CRM_Queue_Task(
          CRM_AssumedPayments_Queue_AssumedPaymentWorker::class . '::run',
          [
            [
              'recur_id' => $recurId,
              'dry_run' => $isDryRun,
            ],
          ],
          'AssumedPayments recur_id=' . $recurId
        )
      );
      $queued++;
    }

    // Result summary
    $result[] = [
      'dryRun' => $isDryRun,
      'from_date' => $from,
      'to_date' => $to,
      'recur_ids' => array_values($ids),
      'count' => count($ids),
      'queue_name' => E::SHOR_NAME . '_schedule',
      'queued' => $queued,
    ];
  }

  /**
   * Returns recur IDs which are relevant for assumed payment scheduling in the given range.
   * - Consider recurs that are "valid" in the window (start_date/end_date bounds)
   * - AND either:
   *   - no contribution instance exists in the window, OR
   *   - a contribution instance exists in the window with an "open" contribution status
   *
   * @param string|null $from
   * @param string|null $to
   * @param list<int> $openStatusIds
   * @return list<int>
   * @throws Civi\Core\Exception\DBQueryException
   */
  private function findRelevantRecurIds(?string $from, ?string $to, array $openStatusIds, int $batchSize): array {
    if (in_array($from, [NULL, '', '0'], TRUE) || in_array($to, [NULL, '', '0'], TRUE)) {
      return [];
    }

    $params = [
      1 => [$from, 'String'],
      2 => [$to, 'String'],
    ];

    // Generate the list of statuses
    $inParts = [];
    $idx = 3;
    foreach ($openStatusIds as $sid) {
      $inParts[] = '%' . $idx;
      $params[$idx] = [(int) $sid, 'Integer'];
      $idx++;
    }
    // empty => no open-status match possible
    $inSql = $inParts !== [] ? implode(',', $inParts) : 'NULL';

    // Generate the Limit Parameter
    $limitSql = '';
    if ($batchSize > 0) {
      $limitSql = ' LIMIT %' . $idx;
      $params[$idx] = [$batchSize, 'Integer'];
    }

    $sql = '
      SELECT DISTINCT recur.id AS recur_id
      FROM civicrm_contribution_recur recur
      -- Aggregate all contributions belonging to a recurring contribution
      LEFT JOIN (
        SELECT
          contrib.contribution_recur_id,
          -- Total number of contributions created for this recurring contribution
          COUNT(*) AS contrib_count,
          -- Number of contributions that are still in an "open" status
          SUM(CASE WHEN contrib.contribution_status_id IN (' . $inSql . ') THEN 1 ELSE 0 END) AS open_count
        FROM civicrm_contribution contrib
        WHERE contrib.is_template = 0
          -- Only consider contributions within the given date range
          AND contrib.receive_date >= %1
          AND contrib.receive_date <= %2
        GROUP BY contrib.contribution_recur_id
      ) contrib
        ON contrib.contribution_recur_id = recur.id
      WHERE recur.is_test = 0
        -- Next scheduled contribution must fall within the given date range
        AND recur.next_sched_contribution_date IS NOT NULL
        AND recur.next_sched_contribution_date >= %1
        AND recur.next_sched_contribution_date <= %2
        AND (recur.start_date IS NULL OR recur.start_date <= %2)
        AND (recur.end_date IS NULL OR recur.end_date >= %1)
        -- Include recurring contributions that:
        -- 1) have not generated any contributions yet OR
        -- 2) still have at least one open contribution
        AND (
          COALESCE(contrib.contrib_count, 0) = 0
          OR COALESCE(contrib.open_count, 0) > 0
        )
      ' . $limitSql;

    /** @var \CRM_Core_DAO $dao */
    $dao = \CRM_Core_DAO::executeQuery($sql, $params);

    $ids = [];
    while ($dao->fetch()) {
      $ids[] = (int) $dao->recur_id;
    }

    // Ensure stable list<int>
    $ids = array_values(array_unique($ids));

    \CRM_Core_Error::debug_var('AssumedPayments params', $params);
    \CRM_Core_Error::debug_var('AssumedPayments ids', $ids);
    return $ids;
  }

  public function setFromDate(?string $value): void {
    $this->fromDate = $value;
  }

  public function setToDate(?string $value): void {
    $this->toDate = $value;
  }

  public function setBatchSize(?int $value): void {
    $this->batchSize = $value;
  }

  public function setDryRun(?bool $value): void {
    $this->dryRun = $value;
  }

  public function setOpenStatusIds(?array $value): void {
    $this->openStatusIds = $value;
  }

}
