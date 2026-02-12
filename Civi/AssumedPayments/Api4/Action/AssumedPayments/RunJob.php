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
 * Input parameters are provided via setters (fromDate, toDate, batchSize, dryRun,
 * openStatusIds) and forwarded to the schedule action after normalization.
 */
final class RunJob extends AbstractAction {


  protected ?string $fromDate = NULL;
  protected ?string $toDate = NULL;
  protected ?int $batchSize = NULL;
  protected ?bool $dryRun = NULL;
  protected null|string|array $openStatusIds = NULL;

  /**
   * Executes the action.
   *
   * Adds exactly one row to the result, containing queue metrics and the schedule
   * action output.
   *
   * @param \Civi\Api4\Generic\Result $result
   *
   * @throws \CRM_Core_Exception
   */
  public function _run(Result $result): void {

    try {
      $action = AssumedPayments::schedule()
        ->setCheckPermissions(FALSE);

      $params = $this->buildScheduleParamsFromValues();

      $action->setFromDate($params['fromDate'] ?? NULL);
      $action->setToDate($params['toDate'] ?? NULL);
      $action->setBatchSize($params['batchSize'] ?? NULL);
      $action->setDryRun($params['dryRun'] ?? NULL);
      $action->setOpenStatusIds($params['openStatusIds'] ?? NULL);

      $row = $action->execute()->first();
    }
    catch (\CRM_Core_Exception) {
      return;
    }

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
   *   Keys match the schedule action setters (fromDate, toDate, batchSize, dryRun, openStatusIds).
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

    $dryRun = $this->dryRun;
    if ($dryRun !== NULL) {
      $params['dryRun'] = $dryRun;
    }

    // openStatusIds can be array or JSON string
    $open = $this->openStatusIds;
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

  public function setDryRun(?bool $value): void {
    $this->dryRun = $value;
  }

  public function setOpenStatusIds(null|string|array $value): void {
    $this->openStatusIds = $value;
  }

}
