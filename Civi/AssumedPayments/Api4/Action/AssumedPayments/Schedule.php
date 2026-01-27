<?php

declare(strict_types = 1);

namespace Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi;
use Civi\Api4\AssumedPayments;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use CRM_AssumedPayments_Queue_AssumedPaymentWorker;

class Schedule extends AbstractAction {

  public function __construct() {
    parent::__construct(AssumedPayments::getEntityName(), 'schedule');
  }

  // API overrides
  protected ?string $fromDate = NULL;
  protected ?string $toDate = NULL;
  protected ?int $limit = NULL;
  protected ?bool $dryRun = NULL;
  protected ?array $openStatusIds = NULL;

  /**
   * @throws DBQueryException
   */
  public function _run(Result $result): void {
    $settings = Civi::settings();

    // Resolve date range: API override > settings
    $from = $this->fromDate ?: $settings->get('assumedpayments_date_from');
    $to = $this->toDate ?: $settings->get('assumedpayments_date_to');

    // Limit
    $limit = $this->limit ?: (int) $settings->get('assumedpayments_batch_size');

    // Dry run
    $isDryRun = $this->dryRun;
    if ($isDryRun === NULL) {
      $isDryRun = (bool) $settings->get('assumedpayments_dry_run_default');
    }

    //Status Ids
    $openStatusIds = $this->openStatusIds;
    if ($openStatusIds === NULL) {
      $openStatusIds = (array) $settings->get('assumedpayments_contribution_status_ids');
    }
    $openStatusIds = array_values(array_filter(array_map('intval', (array) $openStatusIds)));

    // Find relevant recur IDs (DB-side) based on missing contribution in range OR open contribution status in range
    $ids = $this->findRelevantRecurIds($from, $to, $openStatusIds, $limit);

    // Create queue
    $queue = \CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => 'de.systopia.assumedpayments',
      'reset' => TRUE,
    ]);

    $queued = 0;
    foreach ($ids as $recurId) {
      $queue->createItem(
        new \CRM_Queue_Task(
          [CRM_AssumedPayments_Queue_AssumedPaymentWorker::class, 'run'],
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
      'queue_name' => 'de.systopia.assumedpayments',
      'queued' => $queued,
    ];
  }

  /**
   * Returns recur IDs which are relevant for assumed payment scheduling in the given range.
   *
   * Current heuristic (first meaningful step):
   * - Consider recurs that are "valid" in the window (start_date/end_date bounds)
   * - AND either:
   *   - no contribution instance exists in the window, OR
   *   - a contribution instance exists in the window with an "open" contribution status
   *
   * @param string|null $from YYYY-MM-DD (or YYYY-MM-DD HH:MM:SS) or NULL
   * @param string|null $to YYYY-MM-DD (or YYYY-MM-DD HH:MM:SS) or NULL
   * @param list<int> $openStatusIds contribution_status_id values
   * @param int $limit
   * @return list<int>
   * @throws Civi\Core\Exception\DBQueryException
   */
  private function findRelevantRecurIds(?string $from, ?string $to, array $openStatusIds, int $limit): array {
    if (empty($from) || empty($to)) {
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
    $inSql = $inParts ? implode(',', $inParts) : 'NULL';

    // Generate the Limit Parameter
    $limitSql = '';
    if (!empty($limit) && $limit > 0) {
      $limitSql = ' LIMIT %' . $idx;
      $params[$idx] = [(int) $limit, 'Integer'];
    }

    $sql = '
      SELECT DISTINCT recur.id AS recur_id
      FROM civicrm_contribution_recur recur
      LEFT JOIN (
        SELECT
          contrib.contribution_recur_id,
          COUNT(*) AS contrib_count,
          SUM(CASE WHEN contrib.contribution_status_id IN (' . $inSql . ') THEN 1 ELSE 0 END) AS open_count
        FROM civicrm_contribution contrib
        WHERE contrib.is_template = 0
          AND contrib.receive_date >= %1
          AND contrib.receive_date <= %2
        GROUP BY contrib.contribution_recur_id
      ) contrib
        ON contrib.contribution_recur_id = recur.id
      WHERE recur.is_test = 0
        AND recur.next_sched_contribution_date IS NOT NULL
        AND recur.next_sched_contribution_date >= %1
        AND recur.next_sched_contribution_date <= %2
        AND (recur.start_date IS NULL OR recur.start_date <= %2)
        AND (recur.end_date IS NULL OR recur.end_date >= %1)
        AND (
          COALESCE(contrib.contrib_count, 0) = 0
          OR COALESCE(contrib.open_count, 0) > 0
        )
      ' . $limitSql;

    $dao = \CRM_Core_DAO::executeQuery($sql, $params);

    $ids = [];
    while ($dao->fetch()) {
      $ids[] = (int) $dao->recur_id;
    }

    // Ensure stable list<int>
    $ids = array_values(array_unique(array_filter($ids, static fn($v): bool => $v > 0)));
    \CRM_Core_Error::debug_var('AssumedPayments params', $params);
    \CRM_Core_Error::debug_var('AssumedPayments ids', $ids);
    return $ids;
  }

}
