<?php

declare(strict_types = 1);

namespace phpunit\Civi\AssumedPayments\CRM\AssumedPayments\Queue;

use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use CRM_AssumedPayments_Queue_AssumedPaymentWorker;
use CRM_Queue_Queue_Sql;
use CRM_Queue_TaskContext;
use PHPUnit\Framework\TestCase;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionRecurScenario;

/**
 * @covers CRM_AssumedPayments_Queue_AssumedPaymentWorker
 * @group headless
 */
final class AssumedPaymentWorkerTest extends TestCase implements HeadlessInterface, TransactionalInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()->installMe(__DIR__)->apply();
  }

  protected function tearDownHeadless(): void {
    \Civi::settings()->set(
      'assumed_payments_final_contribution_state',
      NULL
    );
  }

  public function testRun_WithoutRecurId_ReturnsFalse(): void {
    $ctx = $this->createQueueContext();
    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, []);
    self::assertFalse($result);
  }

  public function testRun_WithInvalidRecurId_ReturnsFalse(): void {
    $ctx = $this->createQueueContext();

    self::assertFalse(CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => 0]));
    self::assertFalse(CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => -5]));
    self::assertFalse(CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => 999999999]));
  }

  public function testRun_WithValidRecurIdAndWithContribution_ReturnsTrue(): void {
    $ctx = $this->createQueueContext();
    $bag = ContributionRecurScenario::pendingRecurWithPendingContribution();
    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    self::assertTrue($result);
  }

  public function testRun_WithValidRecurId_ReturnsTrue(): void {
    $ctx = $this->createQueueContext();
    $bag = ContributionRecurScenario::pendingRecurWithoutContribution();
    $recurId = (int) $bag->toArray()['recurringContributionId'];
    self::assertGreaterThan(0, $recurId);

    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    self::assertTrue($result);
  }

  public function testRun_CreatesPayment_AndFlagsFinancialTrxn(): void {
    $ctx = $this->createQueueContext();
    $bag = ContributionRecurScenario::pendingRecurWithPendingContribution();
    $recurId = (int) $bag->toArray()['recurringContributionId'];

    $contributionId = $this->getLatestContributionIdForRecur($recurId);
    self::assertGreaterThan(0, $contributionId);

    $assumedBefore = $this->countAssumedFinancialTrxnsForContribution($contributionId);

    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    if (!$result) {
      fwrite(
        STDERR,
        "\nWorker failed: " . json_encode(CRM_AssumedPayments_Queue_AssumedPaymentWorker::$lastFail) . "\n"
      );
    }
    self::assertTrue($result);

    $assumedAfter = $this->countAssumedFinancialTrxnsForContribution($contributionId);
    self::assertSame(
      $assumedBefore + 1,
      $assumedAfter,
      'Worker must create exactly one assumed payment flag for this contribution'
    );

    self::assertGreaterThan(0, $this->countFinancialTrxnsForContribution($contributionId));

    self::assertTrue($this->assumedFlagExistsForContribution($contributionId));

    $statusName = $this->getContributionStatusName($contributionId);
    self::assertSame('Completed', $statusName);
  }

  public function testRun_IsIdempotent_DoesNotCreateSecondPayment(): void {
    $ctx = $this->createQueueContext();
    $bag = ContributionRecurScenario::pendingRecurWithPendingContribution();
    $recurId = (int) $bag->toArray()['recurringContributionId'];

    $contributionId = $this->getLatestContributionIdForRecur($recurId);
    self::assertGreaterThan(0, $contributionId);

    $result1 = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    self::assertTrue($result1);

    $assumedAfterFirst = $this->countAssumedFinancialTrxnsForContribution($contributionId);
    self::assertGreaterThan(0, $assumedAfterFirst, 'First run must create an assumed-flagged financial trxn');

    CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);

    $assumedAfterSecond = $this->countAssumedFinancialTrxnsForContribution($contributionId);
    self::assertSame(
      $assumedAfterFirst,
      $assumedAfterSecond,
      'Second run must not create another assumed-flagged financial trxn'
    );
  }

  public function testRun_WithoutExistingContribution_CreatesContributionAndPayment(): void {
    $ctx = $this->createQueueContext();
    $bag = ContributionRecurScenario::pendingRecurWithoutContribution();
    $recurId = (int) $bag->toArray()['recurringContributionId'];

    $contribBefore = $this->countContributionsForRecur($recurId);

    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    self::assertTrue($result);

    $contribAfter = $this->countContributionsForRecur($recurId);
    self::assertSame($contribBefore + 1, $contribAfter, 'Worker must create a new pending contribution instance');

    $contributionId = $this->getLatestContributionIdForRecur($recurId);
    self::assertGreaterThan(0, $contributionId);

    self::assertSame(1, $this->countFinancialTrxnsForContribution($contributionId));
    self::assertTrue($this->assumedFlagExistsForContribution($contributionId));
  }

  public function testRun_WithCancelledState_SetsContributionToCancelled(): void {
    $ctx = $this->createQueueContext();

    \Civi::settings()->set(
      'assumed_payments_final_contribution_state',
      3
    );

    $bag = ContributionRecurScenario::pendingRecurWithCancelledContribution();
    $recurId = $bag->toArray()['recurringContributionId'];

    $contributionId = $this->getLatestContributionIdForRecur($recurId);
    self::assertGreaterThan(0, $contributionId);

    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, [
      'recur_id' => $recurId,
    ]);

    self::assertTrue($result);

    $statusName = $this->getContributionStatusName($contributionId);

    self::assertSame(
      'Cancelled',
      $statusName,
      'Contribution must be set to the configured final state'
    );
  }

  public function testRun_WithCalculatedDefaultState_SetsContributionToCompleted(): void {
    $ctx = $this->createQueueContext();

    \Civi::settings()->set(
      'assumed_payments_final_contribution_state',
      0
    );

    $bag = ContributionRecurScenario::pendingRecurWithCancelledContribution();
    $recurId = $bag->toArray()['recurringContributionId'];

    $contributionId = $this->getLatestContributionIdForRecur($recurId);
    self::assertGreaterThan(0, $contributionId);

    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, [
      'recur_id' => $recurId,
    ]);

    self::assertTrue($result);

    $statusName = $this->getContributionStatusName($contributionId);

    self::assertSame(
      'Completed',
      $statusName,
      'Contribution must be set to the configured final state'
    );
  }

  /**
   * ---- HELPERS
   */
  private function createQueueContext(): CRM_Queue_TaskContext {
    $spec = [
      'type' => 'Sql',
      'name' => 'assumed_payments-test',
      'reset' => TRUE,
    ];

    $queue = new CRM_Queue_Queue_Sql($spec);
    $ctx = new CRM_Queue_TaskContext();
    $ctx->queue = $queue;
    return $ctx;
  }

  /**
   * @param iterable<mixed> $rows
   * @return array<int>
   */
  private function extractFinancialTrxnIds(iterable $rows): array {
    $ids = [];
    foreach ($rows as $r) {
      /** @var array<string,mixed> $r */
      $id = $r['financial_trxn_id'] ?? 0;
      $ids[] = is_numeric($id) ? (int) $id : 0;
    }
    return array_values(
      array_unique(
        array_filter(
          $ids,
          static fn(int $v): bool => $v !== 0
        )
      )
    );
  }

  private function getLatestContributionIdForRecur(int $recurId): int {
    $row = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('contribution_recur_id', '=', $recurId)
      ->addOrderBy('id', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();

    return (int) ($row['id'] ?? 0);
  }

  private function countAssumedFinancialTrxnsForContribution(int $contributionId): int {
    $rows = \Civi\Api4\EntityFinancialTrxn::get(FALSE)
      ->addSelect('financial_trxn_id')
      ->addWhere('entity_table', '=', 'civicrm_contribution')
      ->addWhere('entity_id', '=', $contributionId)
      ->addWhere('financial_trxn_id.assumed_payments_financialtrxn.is_assumed', '=', TRUE)
      ->setLimit(0)
      ->execute();

    $ids = $this->extractFinancialTrxnIds($rows);
    return count($ids);
  }

  private function countContributionsForRecur(int $recurId): int {
    return \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('contribution_recur_id', '=', $recurId)
      ->execute()
      ->count();
  }

  private function countFinancialTrxnsForContribution(int $contributionId): int {
    $rows = \Civi\Api4\EntityFinancialTrxn::get(FALSE)
      ->addSelect('financial_trxn_id')
      ->addWhere('entity_table', '=', 'civicrm_contribution')
      ->addWhere('entity_id', '=', $contributionId)
      ->addWhere('financial_trxn_id.assumed_payments_financialtrxn.is_assumed', '=', TRUE)
      ->setLimit(0)
      ->execute();

    $ids = $this->extractFinancialTrxnIds($rows);
    return count($ids);
  }

  private function assumedFlagExistsForContribution(int $contributionId): bool {
    $rows = \Civi\Api4\EntityFinancialTrxn::get(FALSE)
      ->addSelect('financial_trxn_id')
      ->addWhere('entity_table', '=', 'civicrm_contribution')
      ->addWhere('entity_id', '=', $contributionId)
      ->addWhere('financial_trxn_id.assumed_payments_financialtrxn.is_assumed', '=', TRUE)
      ->setLimit(0)
      ->execute();

    $ids = $this->extractFinancialTrxnIds($rows);
    if ($ids === []) {
      return FALSE;
    }

    $found = \Civi\Api4\FinancialTrxn::get(FALSE)
      ->addSelect('id')
      ->addWhere('id', 'IN', $ids)
      ->addWhere('assumed_payments_financialtrxn.is_assumed', '=', TRUE)
      ->setLimit(1)
      ->execute()
      ->first();

    return ($found !== NULL);
  }

  private function getContributionStatusName(int $contributionId): string {
    $row = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('contribution_status_id:name')
      ->addWhere('id', '=', $contributionId)
      ->setLimit(1)
      ->execute()
      ->single();

    return (string) ($row['contribution_status_id:name'] ?? '');
  }

}
