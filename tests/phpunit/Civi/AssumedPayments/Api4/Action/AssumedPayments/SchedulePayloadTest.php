<?php

declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionRecurScenario;

/**
 * @covers \Civi\AssumedPayments\Api4\Action\AssumedPayments\Schedule
 */
final class AssumedPaymentsSchedulePayloadTest extends TestCase {

  private ?\CRM_Core_Transaction $tx = NULL;

  protected function setUp(): void {
    parent::setUp();
    $this->tx = new \CRM_Core_Transaction();
  }

  protected function tearDown(): void {
    if ($this->tx !== NULL) {
      $this->tx->rollback();
      $this->tx = NULL;
    }
    parent::tearDown();
  }

  private const QUEUE_NAME = 'de.systopia.assumedpayments';

  public function testRun_EnqueuesQueueTasks_NotArrays(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        'next_sched_contribution_date' => '2025-01-15',
      ]
    );
    $recurId = $bag->toArray()['recurringContributionId'];
    $this->assertGreaterThan(0, $recurId);

    $result = $this->callSchedule([
      'dryRun' => TRUE,
      'limit' => 10,
      'fromDate' => '2000-01-01',
      'toDate' => '2100-01-01',
    ]);

    $this->assertSame(self::QUEUE_NAME, $result['queue_name'] ?? NULL);
    $this->assertIsInt($result['queued'] ?? NULL);
    $this->assertGreaterThanOrEqual(1, (int) $result['queued'], 'Expected schedule to enqueue at least 1 item');

    $payload = $this->fetchLatestQueuePayload(self::QUEUE_NAME);

    $this->assertInstanceOf(
      \CRM_Queue_Task::class,
      $payload,
      'Queue payload must be CRM_Queue_Task so the runner can execute it'
    );
  }

  public function testRun_ProcessesQueuedTasks_AndEmptiesQueue(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        'next_sched_contribution_date' => '2025-01-15',
      ]
    );
    $recurId = $bag->toArray()['recurringContributionId'];
    $this->assertGreaterThan(0, $recurId);

    $row = civicrm_api4('AssumedPayments', 'schedule', [
      'dryRun' => TRUE,
      'limit' => 10,
      'fromDate' => '2000-01-01',
      'toDate' => '2100-01-01',
    ])->first();

    $this->assertIsArray($row);
    $this->assertSame(self::QUEUE_NAME, $row['queue_name'] ?? NULL);
    $this->assertGreaterThanOrEqual(1, (int) ($row['queued'] ?? 0));

    $q = \CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => self::QUEUE_NAME,
    ]);

    $this->assertGreaterThanOrEqual(1, $q->numberOfItems(), 'Expected queue to contain items before runner');

    $runner = new \CRM_Queue_Runner([
      'title' => 'AssumedPayments',
      'queue' => $q,
    ]);
    $runner->runAll();

    $this->assertSame(0, $q->numberOfItems(), 'Expected queue to be empty after runner processed tasks');
  }

  public function testSchedule_DoesNotEnqueueRecur_WhenCompletedContributionExistsInRange(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithCompletedContribution(
      contributionOverrides: [
        'receive_date' => '2025-01-15',
      ]
    );

    $recurId = $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $row = civicrm_api4('AssumedPayments', 'schedule', [
      'dryRun' => TRUE,
      'fromDate' => '2025-01-01',
      'toDate' => '2025-01-31',
      'limit' => 10,
    ])->first();

    self::assertIsArray($row);

    self::assertSame(0, (int) ($row['queued'] ?? 0), 'Completed contribution must not trigger scheduling');
    self::assertNotContains($recurId, $row['recur_ids'] ?? []);
  }

  public function testSchedule_DoesNotEnqueueRecur_WhenStatusDoesNotAllowPaymentAssumption(): void {
    $this->clearQueue(self::QUEUE_NAME);
    $bag = ContributionRecurScenario::pendingRecurWithCancelledContribution(
      contributionOverrides: [
        'receive_date' => '2025-01-15',
      ]
    );

    $recurId = $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $row = civicrm_api4('AssumedPayments', 'schedule', [
      'dryRun' => TRUE,
      'fromDate' => '2025-01-01',
      'toDate' => '2025-01-31',
      'limit' => 10,
    ])->first();

    self::assertIsArray($row);

    self::assertSame(0, (int) ($row['queued'] ?? 0), 'Completed contribution must not trigger scheduling');
    self::assertNotContains($recurId, $row['recur_ids'] ?? []);
  }

  public function testSchedule_DoesEnqueueRecur_WhenStatusDoesAllowPaymentAssumption(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithCancelledContribution(
      contributionOverrides: [
        'receive_date' => '2025-01-15',
      ],
      recurringOverrides: [
        'start_date' => '2025-01-01',
        'end_date' => NULL,
        'next_sched_contribution_date' => '2025-01-15',
      ]
    );

    $recurId = $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $cancelledId = (int) civicrm_api3('OptionValue', 'getvalue', [
      'option_group_id' => 'contribution_status',
      'name' => 'CANCELLED',
      'return' => 'value',
    ]);
    self::assertGreaterThan(0, $cancelledId);

    $row = civicrm_api4('AssumedPayments', 'schedule', [
      'dryRun' => TRUE,
      'fromDate' => '2025-01-01',
      'toDate' => '2025-01-31',
      'limit' => 10,
      'openStatusIds' => [$cancelledId],
    ])->first();

    self::assertIsArray($row);
    self::assertGreaterThanOrEqual(1, (int) ($row['queued'] ?? 0));
    self::assertContains($recurId, $row['recur_ids'] ?? []);
  }

  public function testSchedule_WithMonthlyFrequency_EnqueuesExactlyOneRecur(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $cancelledId = (int) civicrm_api3('OptionValue', 'getvalue', [
      'option_group_id' => 'contribution_status',
      'name' => 'CANCELLED',
      'return' => 'value',
    ]);
    self::assertGreaterThan(0, $cancelledId);

    // Scenario should already create the contribution inside range with cancelled status
    $bag = ContributionRecurScenario::pendingRecurWithCancelledContribution(
      contributionOverrides: [
        'receive_date' => '2025-01-15 00:00:00',
      ],
      recurringOverrides: [
        'start_date' => '2025-01-01',
        'frequency_unit' => 'month',
        'frequency_interval' => 1,
        'end_date' => NULL,
        'next_sched_contribution_date' => '2025-01-15',
      ]
    );

    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $row = civicrm_api4('AssumedPayments', 'schedule', [
      'dryRun' => TRUE,
      'fromDate' => '2025-01-01',
      'toDate' => '2025-01-31',
      'limit' => 10,
      'openStatusIds' => [$cancelledId],
    ])->first();

    self::assertIsArray($row);
    self::assertSame(1, (int) ($row['queued'] ?? 0), 'Schedule enqueues recurs, not instances');
    self::assertContains($recurId, $row['recur_ids'] ?? []);
  }

  /**
   * ---- HELPERS
   */

  /**
   * @param string $queueName
   * @return void
   * @throws \Civi\Core\Exception\DBQueryException
   */
  private function clearQueue(string $queueName): void {
    \CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_queue_item WHERE queue_name = %1',
      [1 => [$queueName, 'String']]
    );
  }

  /**
   * @param array<string, mixed> $params
   * @return array<string, mixed>
   * @throws CRM_Core_Exception
   * @throws \Civi\API\Exception\NotImplementedException
   */
  private function callSchedule(array $params): array {
    $row = civicrm_api4('AssumedPayments', 'schedule', $params)->first();
    $this->assertIsArray($row, 'Expected schedule to return one result row');
    return $row;
  }

  /**
   * @return mixed
   * @throws \Civi\Core\Exception\DBQueryException
   */
  private function fetchLatestQueuePayload(string $queueName) {
    $dao = \CRM_Core_DAO::executeQuery(
      'SELECT data FROM civicrm_queue_item WHERE queue_name = %1 ORDER BY id DESC LIMIT 1',
      [1 => [$queueName, 'String']]
    );

    $this->assertTrue($dao->fetch(), 'Expected at least one queue item');

    $payload = unserialize((string) $dao->data);
    $this->assertNotFalse($payload, 'Expected queue item payload to be unserializable');

    return $payload;
  }

}
