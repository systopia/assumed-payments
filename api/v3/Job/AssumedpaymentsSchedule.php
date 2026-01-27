<?php

declare(strict_types = 1);

/**
 * Scheduled Job: de.systopia.assumedpayments – Schedule + Run Queue
 *
 * This job does not implement business logic. It delegates to the APIv4 action
 * AssumedPayments.schedule (which fills the queue) and then runs the queue.
 *
 * Supported job params (optional):
 * - fromDate (string YYYY-MM-DD or datetime)
 * - toDate (string YYYY-MM-DD or datetime)
 * - limit (int)
 * - dryRun (bool|int)
 * - openStatusIds (array<int>|string JSON)
 *
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 * @throws CRM_Core_Exception
 */
function civicrm_api3_job_assumedpayments_schedule(array $params): array {
  $queueName = 'de.systopia.assumedpayments';

  $api4Params = [];
  if (isset($params['fromDate']) && $params['fromDate'] !== '') {
    $api4Params['fromDate'] = (string) $params['fromDate'];
  }
  if (isset($params['toDate']) && $params['toDate'] !== '') {
    $api4Params['toDate'] = (string) $params['toDate'];
  }
  if (isset($params['limit']) && $params['limit'] !== '') {
    $api4Params['limit'] = (int) $params['limit'];
  }
  if (isset($params['dryRun']) && $params['dryRun'] !== '') {
    $api4Params['dryRun'] = (bool) $params['dryRun'];
  }

  // openStatusIds can be an array or JSON string (because ScheduledJob parameters are often stored as JSON)
  if (isset($params['openStatusIds']) && $params['openStatusIds'] !== '') {
    $open = $params['openStatusIds'];

    if (is_string($open)) {
      $decoded = json_decode($open, TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        $open = $decoded;
      }
    }

    if (is_array($open)) {
      $api4Params['openStatusIds'] = array_values(array_filter(
          array_map('intval', $open), static fn($v): bool => $v > 0)
      );
    }
  }

  // 1) Fill queue via APIv4 action
  $row = civicrm_api4('AssumedPayments', 'schedule', $api4Params)->first();
  $queued = (int) ($row['queued'] ?? 0);

  // 2) Run queue (even if queued==0, to avoid leaving leftovers from prior runs)
  $q = CRM_Queue_Service::singleton()->create([
    'type' => 'Sql',
    'name' => $queueName,
  ]);

  $before = (int) $q->getStatistic('total');

  $runner = new CRM_Queue_Runner([
    'title' => 'AssumedPayments',
    'queue' => $q,
  ]);
  $runner->runAll();

  $after = (int) $q->getStatistic('total');
  $processed = max(0, $before - $after);

  $result = [
    'message' => 'AssumedPayments job executed.',
    'queue_name' => $queueName,
    'scheduled' => $queued,
    'queue_items_before' => $before,
    'processed' => $processed,
    'queue_items_after' => $after,
    'api4_result' => $row,
  ];

  return civicrm_api3_create_success($result, $params, 'Job', 'assumedpayments_schedule');
}
