<?php

declare(strict_types = 1);

use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\Api4\AssumedPayments
 */
final class AssumedPaymentsEntityTest extends TestCase {

  public function testGetFieldsReturnsExpectedActionInstance(): void {
    $action = \Civi\Api4\AssumedPayments::getFields();

    $this->assertInstanceOf(
      \Civi\AssumedPayments\Api4\Action\AssumedPayments\GetFields::class,
      $action
    );
  }

  public function testScheduleReturnsExpectedActionInstance(): void {
    $action = \Civi\Api4\AssumedPayments::schedule();

    $this->assertInstanceOf(
      \Civi\AssumedPayments\Api4\Action\AssumedPayments\Schedule::class,
      $action
    );
  }

}
