<?php

declare(strict_types = 1);

use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\Api4\AssumedPaymentsEntity
 */
final class AssumedPaymentsEntityTest extends TestCase {

  public function testGetFields_ReturnsExpectedActionInstance(): void {
    $action = \Civi\Api4\AssumedPaymentsEntity::getFields();

    $this->assertInstanceOf(
      \Civi\AssumedPayments\Api4\Action\AssumedPayments\GetFields::class,
      $action
    );
  }

  public function testSchedule_ReturnsExpectedActionInstance(): void {
    $action = \Civi\Api4\AssumedPaymentsEntity::schedule();

    $this->assertInstanceOf(
      \Civi\AssumedPayments\Api4\Action\AssumedPayments\Schedule::class,
      $action
    );
  }

  public function testRunJob_ReturnsExpectedActionInstance(): void {
    $action = \Civi\Api4\AssumedPaymentsEntity::runJob();

    $this->assertInstanceOf(
      \Civi\AssumedPayments\Api4\Action\AssumedPayments\RunJob::class,
      $action
    );
  }

}
