<?php

declare(strict_types = 1);

use CRM_AssumedPayments_ExtensionUtil as E;

return [
  [
    'name' => 'OptionValue_cg_extend_objects_financialtrxn',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'cg_extend_objects',
        'label' => E::ts('Financial Transactions'),
        'value' => 'FinancialTrxn',
        'name' => 'civicrm_financial_trxn',
        'is_active' => true,
        'filter' => 0,
        'serialize' => 0,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_assumed_payments_financialtrxn',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'assumed_payments_financialtrxn',
        'table_name' => 'civicrm_value_assumedpayment',
        'title' => 'Assumed Payments',
        'extends' => 'FinancialTrxn',
        'style' => 'Inline',
        'is_active' => TRUE,
        'is_multiple' => FALSE,
        'is_reserved' => TRUE,
        'is_public' => FALSE,
        'collapse_display' => FALSE,
        'collapse_adv_display' => TRUE,
        'weight' => 1,
      ],
    ],
  ],
  [
    'name' => 'CustomField_assumed_payments.is_assumed',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'assumed_payments_financialtrxn',
        'name' => 'is_assumed',
        'label' => 'Payment assumed',
        'data_type' => 'Boolean',
        'html_type' => 'CheckBox',
        'serialize' => 0,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_active' => TRUE,
        'column_name' => 'is_assumed',
      ],
    ],
  ],
];
