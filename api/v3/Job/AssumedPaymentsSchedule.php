<?php

declare(strict_types = 1);

use Civi\Api4\AssumedPayments;

/**
 * Scheduled Job (APIv3 wrapper).
 *
 * Thin APIv3 wrapper around the APIv4 action {@see \Civi\Api4\AssumedPayments::runJob()}.
 * Supported parameters (all optional):
 * - fromDate (string): Start date (YYYY-MM-DD or datetime)
 * - toDate (string): End date (YYYY-MM-DD or datetime)
 * - batchSize (int): Maximum number of items to process
 * - dryRun (bool|int): If set, no changes are persisted
 * - openStatusIds (array<int>|string): Contribution status IDs (array or JSON string)
 *
 * @param array<string, mixed> $params
 *   APIv3 job parameters.
 *
 * @return array<string, mixed>
 *   APIv3 success result containing the first row returned by the APIv4 action.
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_job_assumed_payments_schedule(array $params): array {
  $action = AssumedPayments::runJob()
    ->setCheckPermissions(FALSE);
  $action->setFromDate($params['fromDate'] ?? NULL);
  $action->setToDate($params['toDate'] ?? NULL);
  $action->setBatchSize($params['batchSize'] ?? NULL);
  $action->setDryRun($params['dryRun'] ?? NULL);
  $action->setOpenStatusIds($params['openStatusIds'] ?? NULL);

  $row = $action->execute()->first();

  return civicrm_api3_create_success($row, $params, 'Job', 'assumed_payments_schedule');
}
