<?php

declare(strict_types = 1);

namespace Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi\Api4\AssumedPayments;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use CRM_Queue_Runner;
use CRM_Queue_Service;
use CRM_AssumedPayments_ExtensionUtil as E;

/**
 * APIv4 action: AssumedPayments.runJob
 *
 * Schedules assumed payment tasks via {@see \Civi\Api4\AssumedPayments::schedule()}
 * and then runs the SQL queue {@see CRM_Queue_Runner}.
 *
 * Input parameters are provided via setters (fromDate, toDate, batchSize,
 * openStatusIds) and forwarded to the schedule action after normalization.
 */
final class RunJob extends AbstractAction {

  protected ?string $fromDate = NULL;
  protected ?string $toDate = NULL;
  protected ?int $batchSize = NULL;
  /**
   * @phpstan-var string|list<int>|null $openStatusIds
   */
  protected null|string|array $openStatusIds = NULL;
  /**
   * @phpstan-var string|list<int>|null $paymentInstrumentIds
   */
  protected null|string|array $paymentInstrumentIds = NULL;
  /**
   * @phpstan-var string|list<int>|null $financialTypeIds
   */
  protected null|string|array $financialTypeIds = NULL;

  /**
   * Executes the action.
   *
   * Adds exactly one row to the result, containing queue metrics and the schedule
   * action output.
   *
   * @param \Civi\Api4\Generic\Result $result
   */
  public function _run(Result $result): void {

    try {
      $action = AssumedPayments::schedule()
        ->setCheckPermissions(FALSE);

      $params = $this->buildScheduleParamsFromValues();

      /** @var string $fromDate */
      $fromDate = $params['fromDate'];
      $action->setFromDate($fromDate ?? NULL);

      /** @var string $toDate */
      $toDate = $params['toDate'];
      $action->setToDate($toDate ?? NULL);

      /** @var int $batchSize */
      $batchSize = $params['batchSize'];
      $action->setBatchSize($batchSize ?? NULL);

      /** @var list<int> $openStatusIds */
      $openStatusIds = $params['openStatusIds'];
      $action->setOpenStatusIds($openStatusIds ?? NULL);

      /** @var list<int> $paymentInstrumentIds */
      $paymentInstrumentIds = $params['paymentInstrumentIds'];
      $action->setPaymentInstrumentIds($paymentInstrumentIds ?? NULL);

      /** @var list<int> $financialTypeIds */
      $financialTypeIds = $params['financialTypeIds'];
      $action->setFinancialTypeIds($financialTypeIds ?? NULL);

      $row = $action->execute()->first();
    }
    //@codeCoverageIgnoreStart
    catch (\CRM_Core_Exception) {
      return;
    }
    //@codeCoverageIgnoreEnd

    $queued = (int) ($row['queued'] ?? 0);

    $q = CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => E::LONG_NAME . '_schedule',
    ]);

    $before = (int) $q->getStatistic('total');

    $runner = new CRM_Queue_Runner([
      'title' => 'AssumedPayments',
      'queue' => $q,
    ]);

    $runner->runAll();

    $after = (int) $q->getStatistic('total');
    $processed = max(0, $before - $after);

    $result[] = [
      'message' => 'AssumedPayments job executed.',
      'queue_name' => E::LONG_NAME . '_schedule',
      'scheduled' => $queued,
      'queue_items_before' => $before,
      'processed' => $processed,
      'queue_items_after' => $after,
      'api4_result' => $row,
    ];
  }

  /**
   * Builds the parameter array for the schedule action from action properties.
   *
   * Normalizes optional input values and supports openStatusIds as array<int> or
   * JSON string.
   *
   * @return array<string, mixed>
   *   Keys match the schedule action setters (fromDate, toDate, batchSize, openStatusIds).
   */
  private function buildScheduleParamsFromValues(): array {
    $params = [];

    $fromDate = $this->fromDate;
    if ($fromDate !== NULL && $fromDate !== '') {
      $params['fromDate'] = $fromDate;
    }

    $toDate = $this->toDate;
    if ($toDate !== NULL && $toDate !== '') {
      $params['toDate'] = $toDate;
    }

    $batchSize = $this->batchSize;
    if ($batchSize !== NULL) {
      $params['batchSize'] = $batchSize;
    }

    // openStatusIds can be array or JSON string
    $open = $this->openStatusIds;
    $params['openStatusIds'] = NULL;
    if ($open !== NULL && $open !== '') {
      if (is_array($open)) {
        $params['openStatusIds'] = array_values(
          array_filter(
            array_map(intval(...), $open),
            static fn(int $v): bool => $v > 0
          )
        );
      }
      else {
        $decoded = json_decode($open, TRUE);
        if (json_last_error() === JSON_ERROR_NONE) {
          $params['openStatusIds'] = $decoded;
        }
      }
    }

    // paymentInstrumentIds can be array or JSON string
    $instrumentIds = $this->paymentInstrumentIds;
    $params['paymentInstrumentIds'] = NULL;
    if ($instrumentIds !== NULL && $instrumentIds !== '') {
      if (is_array($instrumentIds)) {
        $params['paymentInstrumentIds'] = array_values(
          array_filter(
            array_map(intval(...), $instrumentIds),
            static fn(int $v): bool => $v > 0
          )
        );
      }
      else {
        $decoded = json_decode($instrumentIds, TRUE);
        if (json_last_error() === JSON_ERROR_NONE) {
          $params['paymentInstrumentIds'] = $decoded;
        }
      }
    }

    // financialTypeIds can be array or JSON string
    $financialTypeIds = $this->financialTypeIds;
    $params['financialTypeIds'] = NULL;
    if ($financialTypeIds !== NULL && $financialTypeIds !== '') {
      if (is_array($financialTypeIds)) {
        $params['financialTypeIds'] = array_values(
          array_filter(
            array_map(intval(...), $financialTypeIds),
            static fn(int $v): bool => $v > 0
          )
        );
      }
      else {
        $decoded = json_decode($financialTypeIds, TRUE);
        if (json_last_error() === JSON_ERROR_NONE) {
          $params['financialTypeIds'] = $decoded;
        }
      }
    }

    return $params;
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
   * @param array<int, int> $value
   * @phpstan-param string|list<int>|null $value
   */
  public function setOpenStatusIds(null|string|array $value): void {
    $this->openStatusIds = $value;
  }

  /**
   * @param array<int, int> $value
   * @phpstan-param string|list<int>|null $value
   */
  public function setPaymentInstrumentIds(null|string|array $value): void {
    $this->paymentInstrumentIds = $value;
  }

  /**
   * @param array<int, int> $value
   * @phpstan-param string|list<int>|null $value
   */
  public function setFinancialTypeIds(null|string|array $value): void {
    $this->financialTypeIds = $value;
  }

}
