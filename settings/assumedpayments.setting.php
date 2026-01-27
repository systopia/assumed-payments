<?php

use CRM_AssumedPayments_ExtensionUtil as E;

return [
  'assumedpayments_relative_date_filter' => [
    'group_name' => 'AssumedPayments Settings',
    'group' => 'assumedpayments',
    'name' => 'assumedpayments_relative_date_filter',
    'type' => 'String',
    'html_type' => 'Select',
    'quick_form_type' => 'Select',
    'title' => E::ts('Date Configuration'),
    'description' => E::ts('Defines a start and end date for the definition of open payments.'),
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => 'previous.month',
    'pseudoconstant' => [
      'optionGroupName' => 'relative_date_filters',
    ],
  ],

  'assumedpayments_contribution_status_ids' => [
    'group_name' => 'AssumedPayments Settings',
    'group' => 'assumedpayments',
    'name' => 'assumedpayments_contribution_status_ids',
    'type' => 'Array',
    'html_type' => 'CheckBox',
    'quick_form_type' => 'CheckBox',
    'title' => E::ts('State Configuration'),
    'description' => E::ts('Defines the states that should apply for the assumption.'),
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => [],
    'serialize' => TRUE,
    'pseudoconstant' => [
      'optionGroupName' => 'contribution_status',
    ],
  ],

  'assumedpayments_batch_size' => [
    'group_name' => 'AssumedPayments Settings',
    'group' => 'assumedpayments',
    'name' => 'assumedpayments_batch_size',
    'type' => 'Integer',
    'html_type' => 'Text',
    'quick_form_type' => 'Text',
    'title' => E::ts('Batch Size'),
    'description' => E::ts('Defines the maximum of rows that should be changed per run.'),
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => 500,
  ],

  'assumedpayments_dry_run_default' => [
    'group_name' => 'AssumedPayments Settings',
    'group' => 'assumedpayments',
    'name' => 'assumedpayments_dry_run_default',
    'type' => 'Boolean',
    'html_type' => 'CheckBox',
    'quick_form_type' => 'CheckBox',
    'title' => E::ts('Dry Run'),
    'description' => E::ts('By Default this will simulate the task and return the results before applying the changes.'),
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => 1,
  ],
];
