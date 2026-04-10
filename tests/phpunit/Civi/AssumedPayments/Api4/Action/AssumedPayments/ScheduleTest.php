<?php

declare(strict_types = 1);


namespace phpunit\Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi\Api4\AssumedPayments;
use Civi\Test\Api4TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\AssumedPayments\Api4\Action\AssumedPayments\Schedule
 */
class ScheduleTest extends TestCase {

  use Api4TestTrait;

  public function testScheduleApiIsCallable(): void {
    $action = AssumedPayments::schedule();
    $action->setBatchSize(1);
    $result = $action->execute();

    self::assertInstanceOf(\Civi\Api4\Generic\Result::class, $result);
    self::assertArrayHasKey(0, $result);

    $row = $result->first() ?? [];

    self::assertArrayHasKey('count', $row);
    self::assertArrayHasKey('recur_ids', $row);
    self::assertIsArray($row['recur_ids'] ?? NULL);
  }

}
