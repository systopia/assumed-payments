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
  /**
   * @phpstan-var list<int>|null
   */
  protected ?array $openStatusIds = NULL;
  /**
   * @phpstan-var list<int>|null
   */
  protected ?array $paymentInstrumentIds = NULL;
  /**
   * @phpstan-var list<int>|null
   */
  protected ?array $financialTypeIds = NULL;

  /**
   * Schedules recurring contributions for assumed payment processing.
   *
   * @param \Civi\Api4\Generic\Result $result
   *
   * @throws \Civi\Core\Exception\DBQueryException
   * @throws \CRM_Core_Exception
   */
  public function _run(Result $result): void {

    // Resolve parameters API override > settings
    $settings = Civi::settings();

    /** @var string|null $fromSetting */
    $fromSetting = $settings->get('assumed_payments_from_date');
    $from = $this->fromDate ?? $fromSetting;

    /** @var string|null $toSetting */
    $toSetting = $settings->get('assumed_payments_to_date');
    $to = $this->toDate ?? $toSetting;

    // Batch Size
    /** @var int|null $batchSettings */
    $batchSettings = $settings->get('assumed_payments_batch_size');
    $batchSize = $this->batchSize
      ?? $batchSettings
      ?? \CRM_AssumedPayments_Settings::DEFAULT_BATCH_SIZE;

    //Status Ids
    $openStatusIds = $this->resolveIntList(
      $this->openStatusIds,
      'assumed_payments_contribution_status_ids'
    );
    $paymentInstrumentIds = $this->resolveIntList(
      $this->paymentInstrumentIds,
      'assumed_payments_payment_instrument_ids'
    );
    $financialTypeIds = $this->resolveIntList(
      $this->financialTypeIds,
      'assumed_payments_financial_type_ids'
    );

    // Find relevant recur IDs (DB-side) based on missing contribution in range OR open contribution status in range
    $ids = $this->findRelevantRecurIds(
      $from,
      $to,
      $openStatusIds,
      $paymentInstrumentIds,
      $financialTypeIds,
      $batchSize
    );

    // Create queue
    $queue = \CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => E::LONG_NAME . '_schedule',
      'reset' => TRUE,
    ]);

    $queued = $this->enqueueRecurIds($queue, $ids);

    // Result summary
    $result[] = [
      'from_date' => $from,
      'to_date' => $to,
      'recur_ids' => array_values($ids),
      'count' => count($ids),
      'queue_name' => E::LONG_NAME . '_schedule',
      'queued' => $queued,
    ];
  }

  /**
   * @phpstan-param ?list<int> $list
   * @return array<int>
   * @phpstan-return list<int>
   */
  private function resolveIntList(?array $list, string $settingsKey): array {
    $settings = Civi::settings();

    $workList = $list;
    if ($workList === NULL) {
      /** @var string|null $setting */
      $setting = $settings->get($settingsKey);

      if ($setting !== NULL) {
        $workList = \CRM_Utils_Array::explodePadded($setting);
      }
    }

    if ($workList === NULL) {
      return [];
    }

    $ids = [];
    foreach ($workList as $v) {
      if (is_int($v)) {
        $ids[] = $v;
      }
      elseif (is_numeric($v)) {
        $ids[] = (int) $v;
      }
    }
    return array_values(array_unique(array_filter($ids, static fn(int $x): bool => $x > 0)));
  }

  /**
   * @param \CRM_Queue_Queue $queue
   * @param iterable<int> $ids
   */
  private function enqueueRecurIds($queue, iterable $ids): int {
    $queued = 0;

    foreach ($ids as $recurId) {
      $queue->createItem(
        new \CRM_Queue_Task(
          CRM_AssumedPayments_Queue_AssumedPaymentWorker::class . '::run',
          [
            [
              'recur_id' => (int) $recurId,
            ],
          ],
          'AssumedPayments recur_id=' . (int) $recurId
        )
      );
      $queued++;
    }

    return $queued;
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
   * @phpstan-param list<int> $openStatusIds
   * @phpstan-param list<int> $paymentInstrumentIds
   * @phpstan-param list<int> $financialTypeIds
   * @return array<int>
   * @throws Civi\Core\Exception\DBQueryException
   */
  private function findRelevantRecurIds(
    ?string $from,
    ?string $to,
    array $openStatusIds,
    array $paymentInstrumentIds,
    array $financialTypeIds,
    int $batchSize
  ): array {
    if (in_array($from, [NULL, '', '0'], TRUE) || in_array($to, [NULL, '', '0'], TRUE)) {
      return [];
    }

    $params = [
      1 => [$from, 'String'],
      2 => [$to, 'String'],
    ];

    // Contribution Status IN
    $inParts = [];
    $idx = 3;
    foreach ($openStatusIds as $sid) {
      $inParts[] = '%' . $idx;
      $params[$idx] = [(int) $sid, 'Integer'];
      $idx++;
    }
    // empty => no open-status match possible
    $inSql = $inParts !== [] ? implode(',', $inParts) : 'NULL';

    // PaymentInstrument IN
    $piParts = [];
    $piSql = '1=1';
    if ($paymentInstrumentIds !== []) {
      foreach ($paymentInstrumentIds as $pid) {
        $piParts[] = '%' . $idx;
        $params[$idx] = [(int) $pid, 'Integer'];
        $idx++;
      }
      $piSql = 'recur.payment_instrument_id IN (' . implode(',', $piParts) . ')';
    }

    // FinancialType IN
    $ftParts = [];
    $ftSql = '1=1';
    if ($financialTypeIds !== []) {
      foreach ($financialTypeIds as $fid) {
        $ftParts[] = '%' . $idx;
        $params[$idx] = [(int) $fid, 'Integer'];
        $idx++;
      }
      $ftSql = 'recur.financial_type_id IN (' . implode(',', $ftParts) . ')';
    }

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
        -- And Payment Instrument IN ...
        AND ( ' . $piSql . ' )
        -- AND Financial Type IN ...
        AND ( ' . $ftSql . ' )
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

  /**
   * @phpstan-param list<int>|null $value
   */
  public function setOpenStatusIds(?array $value): void {
    $this->openStatusIds = $value;
  }

  /**
   * @phpstan-param list<int>|null $value
   */
  public function setPaymentInstrumentIds(?array $value): void {
    $this->paymentInstrumentIds = $value;
  }

  /**
   * @phpstan-param list<int>|null $value
   */
  public function setFinancialTypeIds(?array $value): void {
    $this->financialTypeIds = $value;
  }

}
