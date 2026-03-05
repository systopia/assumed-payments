<?php
declare(strict_types = 1);

namespace Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi\Api4\AssumedPayments;
use Civi\Api4\Generic\BasicGetFieldsAction;
use CRM_AssumedPayments_ExtensionUtil as E;

/**
 * Provides field metadata for AssumedPayments API actions such as `schedule`
 * and `runJob`. This action defines the supported input parameters, their data
 * types, and descriptive labels for use in API consumers and UIs.
 */
class GetFields extends BasicGetFieldsAction {

  public function __construct() {
    parent::__construct(AssumedPayments::getEntityName(), 'getFields');
  }

  /**
   * Returns field definitions for the AssumedPayments API.
   *
   * @phpstan-return list<array<string, mixed>>
   */
  protected function getRecords(): array {
    /** @phpstan-var array<string, array{title: string, description: string}> $mine */
    $mine = \Civi\Api4\Setting::getFields(FALSE)
      ->addSelect('name', 'title', 'description')
      ->addWhere('name', 'LIKE', 'assumed_payments_%')
      ->execute()
      ->indexBy('name')
      ->getArrayCopy();

    return [
      [
        'name' => 'fromDate',
        'title' => $mine['assumed_payments_from_date']['title'],
        'data_type' => 'String',
        'required' => FALSE,
        'description' => $mine['assumed_payments_from_date']['description'],
      ],
      [
        'name' => 'toDate',
        'title' => $mine['assumed_payments_to_date']['title'],
        'data_type' => 'String',
        'required' => FALSE,
        'description' => $mine['assumed_payments_to_date']['description'],
      ],
      [
        'name' => 'batchSize',
        'title' => $mine['assumed_payments_batch_size']['title'],
        'data_type' => 'Integer',
        'required' => FALSE,
        'description' => $mine['assumed_payments_batch_size']['description'],
      ],
      [
        'name' => 'openStatusIds',
        'title' => $mine['assumed_payments_contribution_status_ids']['title'],
        'data_type' => 'Array',
        'required' => FALSE,
        'description' => $mine['assumed_payments_contribution_status_ids']['description'],
      ],
      [
        'name' => 'paymentInstrumentIds',
        'title' => $mine['assumed_payments_payment_instrument_ids']['title'],
        'data_type' => 'Array',
        'required' => FALSE,
        'description' => $mine['assumed_payments_payment_instrument_ids']['description'],
      ],
      [
        'name' => 'financialTypeIds',
        'title' => $mine['assumed_payments_financial_type_ids']['title'],
        'data_type' => 'Array',
        'required' => FALSE,
        'description' => $mine['assumed_payments_financial_type_ids']['description'],
      ],
      [
        'name' => 'finalContributionState',
        'title' => $mine['assumed_payments_final_contribution_state']['title'],
        'data_type' => 'String',
        'required' => FALSE,
        'description' => $mine['assumed_payments_final_contribution_state']['description'],
      ],
    ];
  }

}
