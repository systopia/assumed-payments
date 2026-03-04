<?php

declare(strict_types = 1);

namespace phpunit\Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi\Api4\AssumedPaymentsEntity;
use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionRecurScenario;

/**
 * @covers \Civi\AssumedPayments\Api4\Action\AssumedPayments\RunJob
 * @group headless
 */
final class RunJobTest extends TestCase implements HeadlessInterface, TransactionalInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()->installMe(__DIR__)->apply();
  }

  private const QUEUE_NAME = 'assumed-payments_schedule';

  public function testRunJob_SchedulesAndRunsQueue_AndReturnsMetrics(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        'start_date' => '2025-01-01',
        'next_sched_contribution_date' => '2025-01-15 00:00:00',
      ]
    );
    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $action = AssumedPaymentsEntity::runJob();
    $action->setBatchSize(10);
    $action->setFromDate('2025-01-01');
    $action->setToDate('2025-01-31');
    $action->setOpenStatusIds([1]);
    $action->setFinancialTypeIds([1]);
    $action->setPaymentInstrumentIds([1]);

    $result = $action->execute();
    self::assertSame(1, $result->count());

    $row = $result->first();
    self::assertIsArray($row);

    self::assertSame('AssumedPayments job executed.', $row['message'] ?? NULL);
    self::assertSame(self::QUEUE_NAME, $row['queue_name'] ?? NULL);

    self::assertArrayHasKey('scheduled', $row);
    self::assertArrayHasKey('queue_items_before', $row);
    self::assertArrayHasKey('processed', $row);
    self::assertArrayHasKey('queue_items_after', $row);
    self::assertArrayHasKey('api4_result', $row);

    self::assertIsInt($row['scheduled']);
    self::assertIsInt($row['queue_items_before']);
    self::assertIsInt($row['processed']);
    self::assertIsInt($row['queue_items_after']);

    self::assertGreaterThanOrEqual(1, (int) $row['scheduled']);
    self::assertGreaterThanOrEqual(1, (int) $row['queue_items_before']);

    self::assertGreaterThanOrEqual(1, (int) $row['processed']);
    self::assertSame(0, (int) $row['queue_items_after']);

    self::assertIsArray($row['api4_result']);
    self::assertArrayHasKey('queued', $row['api4_result']);
    self::assertContains($recurId, $row['api4_result']['recur_ids'] ?? []);
  }

  public function testRunJob_WithJsonIds_ReturnsMetrics(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        'start_date' => '2025-01-01',
        'next_sched_contribution_date' => '2025-01-15 00:00:00',
      ]
    );
    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $action = AssumedPaymentsEntity::runJob();
    $action->setBatchSize(10);
    $action->setFromDate('2025-01-01');
    $action->setToDate('2025-01-31');
    $action->setOpenStatusIds(json_encode([1]));

    $result = $action->execute();
    self::assertSame(1, $result->count());
  }

  /**
   * @throws \Civi\Core\Exception\DBQueryException
   */
  private function clearQueue(string $queueName): void {
    \CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_queue_item WHERE queue_name = %1',
      [1 => [$queueName, 'String']]
    );
  }

}
