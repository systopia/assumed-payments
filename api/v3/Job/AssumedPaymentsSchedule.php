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
 * - openStatusIds (array<int>|string): Contribution status ids (array or JSON string)
 * - paymentInstrumentIds (array<int>|string): Payment Instrument ids (array or JSON string)
 * - financialTypeIds (array<int>|string): Financial Type ids (array or JSON string)
 *
 * @param array<string, mixed> $params
 *   APIv3 job parameters.
 *
 * @return array<string, mixed>
 *   APIv3 success result containing the first row returned by the APIv4 action.
 *
 * @throws \CRM_Core_Exception
 */
// phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
function civicrm_api3_job_assumed_payments_schedule(array $params): array {
  $action = AssumedPayments::runJob()
    ->setCheckPermissions(FALSE);

  $fromDate = $params['fromDate'] ?? NULL;
  if (is_string($fromDate) && strtotime($fromDate) !== FALSE) {
    $action->setFromDate($fromDate);
  }

  $toDate = $params['toDate'] ?? NULL;
  if (is_string($toDate) && strtotime($toDate) !== FALSE) {
    $action->setToDate($toDate);
  }

  $batchSize = ($params['batchSize'] ?? NULL);
  $action->setBatchSize(is_numeric($batchSize) ? (int) $batchSize : NULL);

  /** @var string|list<int> $openStatusIds */
  $openStatusIds = ($params['openStatusIds'] ?? NULL);
  $action->setOpenStatusIds(is_array($openStatusIds) || is_string($openStatusIds) ? $openStatusIds : NULL);

  /** @var string|list<int> $paymentInstrumentIds */
  $paymentInstrumentIds = ($params['paymentInstrumentIds'] ?? NULL);
  $action->setPaymentInstrumentIds(
    is_array($paymentInstrumentIds) || is_string($paymentInstrumentIds) ? $paymentInstrumentIds : NULL
  );

  /** @var string|list<int> $financialTypeIds */
  $financialTypeIds = ($params['financialTypeIds'] ?? NULL);
  $action->setFinancialTypeIds(is_array($financialTypeIds) || is_string($financialTypeIds) ? $financialTypeIds : NULL);

  $row = $action->execute()->first();

  if ($row === NULL) {
    return [];
  }

  return civicrm_api3_create_success($row, $params, 'Job', 'assumed_payments_schedule');
}
