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
    //TODO: How can we access the settings here to avoid redundancy?
    return [
      [
        'name' => 'fromDate',
        'title' => E::ts('From date'),
        'data_type' => 'String',
        'required' => FALSE,
        'description' => E::ts('Start date (YYYY-MM-DD) for scheduling assumed payments'),
      ],
      [
        'name' => 'toDate',
        'title' => E::ts('To date'),
        'data_type' => 'String',
        'required' => FALSE,
        'description' => E::ts('End date (YYYY-MM-DD) for scheduling assumed payments'),
      ],
      [
        'name' => 'batchSize',
        'title' => E::ts('Batch Size'),
        'data_type' => 'Integer',
        'required' => FALSE,
        'description' => E::ts('Maximum number of recurring contributions to process'),
      ],
      [
        'name' => 'dryRun',
        'title' => E::ts('Dry Run'),
        'data_type' => 'Boolean',
        'required' => FALSE,
        'description' => E::ts('If enabled, no contributions or payments are created'),
      ],
      [
        'name' => 'openStatusIds',
        'title' => E::ts('Status Ids'),
        'data_type' => 'Array',
        'required' => FALSE,
        'description' => E::ts('Status Ids of Contributions considered as "Open"'),
      ],
    ];
  }

}
