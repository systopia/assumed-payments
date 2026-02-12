<?php

declare(strict_types = 1);

use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\Api4\AssumedPayments
 * @group headless
 */
final class AssumedPaymentsEntityTest extends TestCase implements HeadlessInterface, TransactionalInterface {

  /**
   * {@inheritDoc}
   */
  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

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
