<?php

declare(strict_types = 1);

namespace phpunit\Civi\AssumedPayments\Api4\Action\AssumedPayments;

use Civi\Api4\AssumedPayments;
use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionRecurScenario;

/**
 * @covers \Civi\AssumedPayments\Api4\Action\AssumedPayments\Schedule
 * @group headless
 */
final class SchedulePayloadTest extends TestCase implements HeadlessInterface, TransactionalInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()->installMe(__DIR__)->apply();
  }

  private const QUEUE_NAME = 'assumed-payments_schedule';

  public function testRun_EnqueuesQueueTasks_NotArrays(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        'next_sched_contribution_date' => '2025-01-15',
      ]
    );
    $recurId = $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $action = AssumedPayments::schedule();
    $action->setBatchSize(10);
    $action->setFromDate('2000-01-01');
    $action->setToDate('2100-01-01');

    $result = $action->execute()->first();

    self::assertIsArray($result, 'Expected schedule to return one result row');
    self::assertSame(self::QUEUE_NAME, $result['queue_name'] ?? NULL);
    self::assertIsInt($result['queued'] ?? NULL);
    self::assertGreaterThanOrEqual(1, $result['queued'], 'Expected schedule to enqueue at least 1 item');

    $payload = $this->fetchLatestQueuePayload(self::QUEUE_NAME);

    self::assertInstanceOf(
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
    self::assertGreaterThan(0, $recurId);

    $action = AssumedPayments::schedule();
    $action->setBatchSize(10);
    $action->setFromDate('2000-01-01');
    $action->setToDate('2100-01-01');

    $row = $action->execute()->first();

    self::assertIsArray($row);
    self::assertSame(self::QUEUE_NAME, $row['queue_name'] ?? NULL);
    self::assertGreaterThanOrEqual(1, (int) ($row['queued'] ?? 0));

    $q = \CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => self::QUEUE_NAME,
    ]);

    self::assertGreaterThanOrEqual(1, $q->numberOfItems(), 'Expected queue to contain items before runner');

    $runner = new \CRM_Queue_Runner([
      'title' => 'AssumedPayments',
      'queue' => $q,
    ]);
    $runner->runAll();

    self::assertSame(0, $q->numberOfItems(), 'Expected queue to be empty after runner processed tasks');
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

    $action = AssumedPayments::schedule();
    $action->setBatchSize(10);
    $action->setFromDate('2025-01-01');
    $action->setToDate('2025-01-31');
    $row = $action->execute()->first();

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

    $action = AssumedPayments::schedule();
    $action->setBatchSize(10);
    $action->setFromDate('2025-01-01');
    $action->setToDate('2025-01-31');

    $row = $action->execute()->first();
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

    $action = AssumedPayments::schedule();
    $action->setBatchSize(10);
    $action->setFromDate('2025-01-01');
    $action->setToDate('2025-01-31');
    $action->setOpenStatusIds([$cancelledId]);

    $row = $action->execute()->first();

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

    $action = AssumedPayments::schedule();
    $action->setBatchSize(10);
    $action->setFromDate('2025-01-01');
    $action->setToDate('2025-01-31');
    $action->setOpenStatusIds([$cancelledId]);

    $row = $action->execute()->first();

    self::assertIsArray($row);
    self::assertSame(1, (int) ($row['queued'] ?? 0), 'Schedule enqueues recurs, not instances');
    self::assertContains($recurId, $row['recur_ids'] ?? []);
  }

  public function testSchedule_WithValidPaymentInstrument_EnqueuesExactlyOneRecur(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        'start_date' => '2025-01-01',
        'next_sched_contribution_date' => '2025-01-15 00:00:00',
        'payment_instrument_id' => 1,
      ]
    );

    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $action = AssumedPayments::schedule();
    $action->setBatchSize(10);
    $action->setFromDate('2025-01-01');
    $action->setToDate('2025-01-31');
    $action->setPaymentInstrumentIds([1]);

    $row = $action->execute()->first();

    self::assertIsArray($row);
    self::assertSame(1, (int) ($row['queued'] ?? 0), 'Schedule enqueues recurs, not instances');
    self::assertContains($recurId, $row['recur_ids'] ?? []);
  }

  public function testSchedule_WithInvalidPaymentInstrument_DoesNotEnqueue(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        'start_date' => '2025-01-01',
        'next_sched_contribution_date' => '2025-01-15 00:00:00',
        'payment_instrument_id' => 1,
      ]
    );

    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $action = AssumedPayments::schedule();
    $action->setBatchSize(10);
    $action->setFromDate('2025-01-01');
    $action->setToDate('2025-01-31');
    $action->setPaymentInstrumentIds([2]);

    $row = $action->execute()->first();

    self::assertIsArray($row);
    self::assertSame(0, (int) ($row['queued'] ?? 0), 'Payment Instrument mismatch must not trigger scheduling');
    self::assertNotContains($recurId, $row['recur_ids'] ?? []);
  }

  public function testSchedule_WithValidFinancialType_EnqueuesExactlyOneRecur(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        'start_date' => '2025-01-01',
        'next_sched_contribution_date' => '2025-01-15 00:00:00',
        'financial_type_id:name' => 'Donation',
      ]
    );

    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $action = AssumedPayments::schedule();
    $action->setBatchSize(10);
    $action->setFromDate('2025-01-01');
    $action->setToDate('2025-01-31');
    $action->setFinancialTypeIds([1]);

    $row = $action->execute()->first();

    self::assertIsArray($row);
    self::assertSame(1, (int) ($row['queued'] ?? 0), 'Schedule enqueues recurs, not instances');
    self::assertContains($recurId, $row['recur_ids'] ?? []);
  }

  public function testSchedule_WithInvalidFinancialTypeId_DoesNotEnqueue(): void {
    $this->clearQueue(self::QUEUE_NAME);

    $bag = ContributionRecurScenario::pendingRecurWithoutContribution(
      recurringOverrides: [
        'start_date' => '2025-01-01',
        'next_sched_contribution_date' => '2025-01-15 00:00:00',
        'financial_type_id:name' => 'Donation',
      ]
    );
    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    // allow only the other FT -> should filter it out
    $action = AssumedPayments::schedule();
    $action->setBatchSize(10);
    $action->setFromDate('2025-01-01');
    $action->setToDate('2025-01-31');
    $action->setFinancialTypeIds([2]);

    $row = $action->execute()->first();

    self::assertIsArray($row);
    self::assertSame(0, (int) ($row['queued'] ?? 0), 'Financial Type mismatch must not trigger scheduling');
    self::assertNotContains($recurId, $row['recur_ids'] ?? []);
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
   * @return mixed
   * @throws \Civi\Core\Exception\DBQueryException
   */
  private function fetchLatestQueuePayload(string $queueName) {
    /** @phpstan-var \CRM_Core_DAO $dao */
    $dao = \CRM_Core_DAO::executeQuery(
      'SELECT data FROM civicrm_queue_item WHERE queue_name = %1 ORDER BY id DESC LIMIT 1',
      [1 => [$queueName, 'String']]
    );

    self::assertTrue($dao->fetch(), 'Expected at least one queue item');

    $payload = unserialize((string) $dao->data);
    self::assertNotFalse($payload, 'Expected queue item payload to be unserializable');

    return $payload;
  }

}
