<?php

declare(strict_types = 1);


namespace phpunit\Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi\Test\Api4TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\AssumedPayments\Api4\Action\AssumedPayments\Schedule
 */
class ScheduleTest extends TestCase {

  use Api4TestTrait;

  public function testScheduleApiIsCallable(): void {
    $result = civicrm_api4('AssumedPayments', 'schedule', [
      'dryRun' => TRUE,
      'limit' => 1,
    ]);

    $this->assertInstanceOf(\Civi\Api4\Generic\Result::class, $result);
    $this->assertArrayHasKey(0, $result);

    $row = $result[0];

    $this->assertArrayHasKey('count', $row);
    $this->assertArrayHasKey('recur_ids', $row);
    $this->assertIsArray($row['recur_ids']);
  }

}
