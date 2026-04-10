<?php

declare(strict_types = 1);

return [
  [
    'name' => 'AssumedPayments_ScheduledJob',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'Assumed Payments – Schedule',
      'description' => 'Schedules and processes assumed payments for recurring contributions that appear unpaid within a configured date range.',
      'run_frequency' => 'Daily',
      'api_entity' => 'Job',
      'api_action' => 'assumed_payments_schedule',
      'parameters' => '',
      'is_active' => 0,
    ],
  ],
];
