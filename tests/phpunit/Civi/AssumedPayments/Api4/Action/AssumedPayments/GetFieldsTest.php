<?php

declare(strict_types = 1);

namespace phpunit\Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi\Api4\Generic\Result;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\AssumedPayments\Api4\Action\AssumedPayments\GetFields
 * @group headless
 */
class GetFieldsTest extends TestCase implements HeadlessInterface, TransactionalInterface {

  /**
   * {@inheritDoc}
   */
  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function testGetFields_GetRecords_ReturnsExpectedFields(): void {
    $result = civicrm_api4('AssumedPayments', 'getFields', []);
    $this->assertInstanceOf(Result::class, $result);

    $fields = array_column($result->getArrayCopy(), 'name');

    self::assertContains('fromDate', $fields);
    self::assertContains('toDate', $fields);
    self::assertContains('limit', $fields);
    self::assertContains('dryRun', $fields);
  }

}
