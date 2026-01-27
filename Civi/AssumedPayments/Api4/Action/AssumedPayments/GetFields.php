<?php
declare(strict_types = 1);

namespace Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi\Api4\AssumedPayments;
use Civi\Api4\Generic\BasicGetFieldsAction;
use CRM_Assumedpayments_ExtensionUtil as E;

class GetFields extends BasicGetFieldsAction {

  public function __construct() {
    parent::__construct(AssumedPayments::getEntityName(), 'getFields');
  }

  /**
   * @phpstan-return list<array<string, mixed>>
   */
  protected function getRecords(): array {
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
        'name' => 'limit',
        'title' => E::ts('Limit'),
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
