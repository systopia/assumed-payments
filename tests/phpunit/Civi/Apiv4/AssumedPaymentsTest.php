<?php

declare(strict_types = 1);

use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\Api4\AssumedPayments
 */
final class AssumedPaymentsTest extends TestCase {

  public function testGetFields_ReturnsExpectedActionInstance(): void {
    $action = \Civi\Api4\AssumedPayments::getFields();

    self::assertInstanceOf(
      \Civi\AssumedPayments\Api4\Action\AssumedPayments\GetFields::class,
      $action
    );
  }

  public function testSchedule_ReturnsExpectedActionInstance(): void {
    $action = \Civi\Api4\AssumedPayments::schedule();

    self::assertInstanceOf(
      \Civi\AssumedPayments\Api4\Action\AssumedPayments\Schedule::class,
      $action
    );
  }

  public function testRunJob_ReturnsExpectedActionInstance(): void {
    $action = \Civi\Api4\AssumedPayments::runJob();

    self::assertInstanceOf(
      \Civi\AssumedPayments\Api4\Action\AssumedPayments\RunJob::class,
      $action
    );
  }

}
