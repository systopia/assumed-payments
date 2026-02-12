<?php

declare(strict_types = 1);

use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionRecurScenario;

/**
 * @covers ::civicrm_api3_job_assumed_payments_schedule
 * @group headless
 */
final class AssumedPaymentsScheduleTest extends TestCase implements HeadlessInterface {

  /**
   * {@inheritDoc}
   */
  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }
  private ?\CRM_Core_Transaction $tx = NULL;

  private const QUEUE_NAME = 'assumed_payments';

  protected function setUp(): void {
    parent::setUp();
    $this->tx = new \CRM_Core_Transaction();
    $this->clearQueue(self::QUEUE_NAME);
  }

  protected function tearDown(): void {
    if ($this->tx) {
      $this->tx->rollback();
      $this->tx = NULL;
    }
    parent::tearDown();
  }

  public function testJob_SchedulesAndProcessesQueue_DryRun(): void {
    // 1) Fixture: create recur that is due in window
    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        // critical: make due deterministic
        'next_sched_contribution_date' => '2025-01-15 00:00:00',
        'start_date' => '2025-01-01',
        'end_date' => NULL,
      ]
    );

    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    // 2) Run job (calls APIv4 schedule + runs queue)
    $res = civicrm_api3('Job', 'assumed_payments_schedule', [
      'fromDate' => '2025-01-01',
      'toDate' => '2025-01-31',
      'dryRun' => 1,
      'limit' => 10,
    ]);

    self::assertSame(0, (int) ($res['is_error'] ?? 1));
    $values = $res['values'] ?? [];
    self::assertIsArray($values);
    self::assertSame(self::QUEUE_NAME, $values['queue_name'] ?? NULL);
    self::assertGreaterThanOrEqual(1, (int) ($values['scheduled'] ?? 0));
    self::assertGreaterThanOrEqual(1, (int) ($values['processed'] ?? 0));
    self::assertSame(0, (int) ($values['queue_items_after'] ?? -1), 'Queue must be empty after job run');

    // 3) Verify DB: queue empty
    self::assertSame(0, $this->queueCount(self::QUEUE_NAME));
  }

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
      'dryRun' => 1,
      'limit' => 10,
    ]);

    self::assertSame(0, (int) ($res['is_error'] ?? 1));
    $values = $res['values'] ?? [];
    self::assertIsArray($values);
    self::assertSame(0, (int) ($values['scheduled'] ?? -1));
    self::assertSame(0, (int) ($values['queue_items_after'] ?? -1));
    self::assertSame(0, $this->queueCount(self::QUEUE_NAME));
  }

  /**
   * ---- helpers
   */
  private function clearQueue(string $queueName): void {
    \CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_queue_item WHERE queue_name = %1',
      [1 => [$queueName, 'String']]
    );
  }

  private function queueCount(string $queueName): int {
    return (int) \CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM civicrm_queue_item WHERE queue_name = %1',
      [1 => [$queueName, 'String']]
    );
  }

}
