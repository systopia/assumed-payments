<?php

return [
  [
    'name' => 'AssumedPayments_ScheduledJob',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'Assumed Payments – Schedule',
      'description' => 'TODO:',
      'run_frequency' => 'Daily',
      'api_entity' => 'Job',
      'api_action' => 'assumedpayments_schedule',
      'parameters' => '',
      'is_active' => 0,
    ],
  ],
];
