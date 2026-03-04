<?php

declare(strict_types = 1);

namespace phpunit\Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi\Api4\Generic\Result;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Civi\AssumedPayments\Api4\Action\AssumedPayments\GetFields
 */
class GetFieldsTest extends TestCase {

  public function testGetFields_GetRecords_ReturnsExpectedFields(): void {
    $result = \Civi\Api4\AssumedPayments::getFields()->execute();
    self::assertInstanceOf(Result::class, $result);
    $fields = array_column($result->getArrayCopy(), 'name');
    self::assertContains('fromDate', $fields);
    self::assertContains('toDate', $fields);
    self::assertContains('batchSize', $fields);
    self::assertContains('openStatusIds', $fields);
    self::assertContains('paymentInstrumentIds', $fields);
    self::assertContains('financialTypeIds', $fields);
    self::assertContains('finalContributionState', $fields);
  }

}
