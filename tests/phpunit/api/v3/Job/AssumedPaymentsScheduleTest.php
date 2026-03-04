<?php

declare(strict_types = 1);

use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionRecurScenario;

/**
 * @covers ::civicrm_api3_job_assumed_payments_schedule
 * @group headless
 */
final class AssumedPaymentsScheduleTest extends TestCase implements HeadlessInterface, TransactionalInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()->installMe(__DIR__)->apply();
  }

  private const QUEUE_NAME = 'assumed-payments_schedule';

  public function testJob_WhenNothingDue_SchedulesZero_AndQueueEmpty(): void {
    // Fixture: recur not due in window
    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        // outside Feb
        'next_sched_contribution_date' => '2025-03-15 00:00:00',
        'start_date' => '2025-01-01',
        'end_date' => NULL,
      ]
    );

    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $res = civicrm_api3('Job', 'assumed_payments_schedule', [
      'fromDate' => '2025-02-01',
      'toDate' => '2025-02-28',
      'batchSize' => 10,
    ]);

    self::assertSame(0, (int) ($res['is_error'] ?? 1));
    /** @phpstan-var array{scheduled?: int, queue_items_after?: int} $values */
    $values = $res['values'] ?? [];
    self::assertIsArray($values);
    self::assertSame(0, (int) ($values['scheduled'] ?? -1));
    self::assertSame(0, (int) ($values['queue_items_after'] ?? -1));
    self::assertSame(0, $this->queueCount(self::QUEUE_NAME));
  }

  /**
   * ---- helpers
   */
  private function queueCount(string $queueName): int {
    return (int) \CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM civicrm_queue_item WHERE queue_name = %1',
      [1 => [$queueName, 'String']]
    );
  }

}
