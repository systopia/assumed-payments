<?php

declare(strict_types = 1);

use CRM_AssumedPayments_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_Contributions_with_is_assumed_Flag',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Contributions_with_is_assumed_Flag',
        'label' => E::ts('Contributions with "is_assumed" Flag'),
        'api_entity' => 'Contribution',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'receive_date',
            'contact_id.sort_name',
            'total_amount',
            'financial_type_id:label',
            'contribution_status_id:label',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [],
          'join' => [
            [
              'FinancialTrxn AS Contribution_EntityFinancialTrxn_FinancialTrxn_01',
              'INNER',
              'EntityFinancialTrxn',
              [
                'id',
                '=',
                'Contribution_EntityFinancialTrxn_FinancialTrxn_01.entity_id',
              ],
              [
                'Contribution_EntityFinancialTrxn_FinancialTrxn_01.entity_table',
                '=',
                "'civicrm_contribution'",
              ],
              [
                'Contribution_EntityFinancialTrxn_FinancialTrxn_01.assumed_payments_financialtrxn.is_assumed',
                '=',
                TRUE,
              ],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
