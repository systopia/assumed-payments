<?php

declare(strict_types = 1);

use CRM_AssumedPayments_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_Queue_Items_for_unpaid_Recurs',
    'entity' => 'SavedSearch',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Queue_Items_for_unpaid_Recurs',
        'label' => E::ts('Queue Items for unpaid Recurs'),
        'api_entity' => 'QueueItem',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
          ],
          'orderBy' => [],
          'where' => [
            [
              'queue_name',
              '=',
              'assumed_payments_schedule',
            ],
          ],
          'groupBy' => [],
          'join' => [],
          'having' => [],
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
