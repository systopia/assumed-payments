<?php

declare(strict_types = 1);

use CRM_AssumedPayments_ExtensionUtil as E;

return [

  'assumed_payments_payment_instrument_ids' => [
    'group_name' => 'AssumedPayments Settings',
    'type' => 'Array',
    'html_type' => 'checkboxes',
    'title' => E::ts('Payment instruments'),
    'description' => E::ts('Allowed payment instruments for recurring contributions considered as "unpaid".'),
    'is_domain' => 1,
    'default' => [],
    'serialize' => TRUE,
    'pseudoconstant' => [
      'callback' => 'CRM_AssumedPayments_Settings::paymentInstrumentOptions',
    ],
    'settings_pages' => ['assumed_payments' => ['weight' => 1]],
  ],

  'assumed_payments_financial_type_ids' => [
    'group_name' => 'AssumedPayments Settings',
    'type' => 'Array',
    'html_type' => 'checkboxes',
    'title' => E::ts('Financial Types'),
    'description' => E::ts('Allowed financial types for recurring contributions considered as "unpaid".'),
    'is_domain' => 1,
    'default' => [],
    'serialize' => TRUE,
    'pseudoconstant' => [
      'callback' => 'CRM_AssumedPayments_Settings::financialTypeOptions',
    ],
    'settings_pages' => ['assumed_payments' => ['weight' => 2]],
  ],

  'assumed_payments_from_date' => [
    'group_name' => 'AssumedPayments Settings',
    'type' => 'String',
    'html_type' => 'datepicker',
    'title' => E::ts('Date Range Start'),
    'description' => E::ts('Defines the start date for the definition of open payments.'),
    'is_domain' => 1,
    'settings_pages' => ['assumed_payments' => ['weight' => 4]],
  ],

  'assumed_payments_to_date' => [
    'group_name' => 'AssumedPayments Settings',
    'type' => 'String',
    'html_type' => 'datepicker',
    'title' => E::ts('Date Range End'),
    'description' => E::ts('Defines the end date for the definition of open payments.'),
    'is_domain' => 1,
    'settings_pages' => ['assumed_payments' => ['weight' => 5]],
  ],

  'assumed_payments_contribution_status_ids' => [
    'group_name' => 'AssumedPayments Settings',
    'type' => 'Array',
    'html_type' => 'checkboxes',
    'title' => E::ts('States considered "unpaid"'),
    'description' => E::ts('The chosen contribution states that act as "unpaid" and will trigger the job.'),
    'is_domain' => 1,
    'default' => [],
    'serialize' => TRUE,
    'pseudoconstant' => [
      'callback' => 'CRM_AssumedPayments_Settings::contributionStatusOptions',
    ],
    'settings_pages' => ['assumed_payments' => ['weight' => 6]],
  ],

  'assumed_payments_batch_size' => [
    'group_name' => 'AssumedPayments Settings',
    'type' => 'Integer',
    'html_type' => 'text',
    'title' => E::ts('Batch Size'),
    'description' => E::ts('Defines the maximum of rows that should be changed per run.'),
    'is_domain' => 1,
    'default' => 500,
    'settings_pages' => ['assumed_payments' => ['weight' => 8]],
  ],

  'assumed_payments_final_contribution_state' => [
    'group_name' => 'AssumedPayments Settings',
    'type' => 'Array',
    'html_type' => 'select',
    'title' => E::ts('Modified Contribution State'),
    'description' => E::ts('The state the flagged contribution will turn into after being processed.'),
    'is_domain' => 1,
    'default' => [],
    'serialize' => TRUE,
    'pseudoconstant' => [
      'callback' => 'CRM_AssumedPayments_Settings::contributionFinalStatusOptions',
    ],
    'settings_pages' => ['assumed_payments' => ['weight' => 7]],
  ],
];
