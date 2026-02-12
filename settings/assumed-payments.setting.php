<?php

use CRM_AssumedPayments_ExtensionUtil as E;

return [
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
    'title' => E::ts('State Configuration'),
    'description' => E::ts('Defines the states that should apply for the assumption.'),
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

  'assumed_payments_dry_run_default' => [
    'group_name' => 'AssumedPayments Settings',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'title' => E::ts('Dry Run'),
    'description' =>
    E::ts('By Default this will simulate the task and return the results before applying the changes.'),
    'is_domain' => 1,
    'default' => 1,
    'settings_pages' => ['assumed_payments' => ['weight' => 10]],
  ],
];
