<?php

declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionRecurScenario;

/**
 * @covers CRM_AssumedPayments_Queue_AssumedPaymentWorker
 */
final class CRM_AssumedPayments_Queue_AssumedPaymentWorkerTest extends TestCase {

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

  public function testRun_WithoutRecurId_ReturnsFalse(): void {
    $ctx = $this->createQueueContext();
    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, []);
    $this->assertFalse($result);
  }

  public function testRun_WithInvalidRecurId_ReturnsFalse(): void {
    $ctx = $this->createQueueContext();
    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => 0]);
    $this->assertFalse($result);
    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => -5]);
    $this->assertFalse($result);
    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => 999999999]);
    $this->assertFalse($result);
  }

  public function testRun_WithValidRecurIdAndWithContribution_ReturnsTrue(): void {
    $ctx = $this->createQueueContext();
    $bag = ContributionRecurScenario::pendingRecurWithPendingContribution();
    $recurId = $bag->toArray()['recurringContributionId'];
    $this->assertGreaterThan(0, $recurId, 'Fixture must provide a valid ContributionRecur id');
    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    $this->assertTrue($result);
  }

  public function testRun_WithValidRecurId_ReturnsTrue(): void {
    $ctx = $this->createQueueContext();
    $bag = ContributionRecurScenario::pendingRecurWithoutContribution();
    $recurId = $bag->toArray()['recurringContributionId'];
    $this->assertGreaterThan(0, $recurId, 'Fixture must provide a valid ContributionRecur id');
    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    $this->assertTrue($result);
  }

  public function testRun_CreatesPayment_AndFlagsFinancialTrxn(): void {
    $ctx = $this->createQueueContext();
    $bag = ContributionRecurScenario::pendingRecurWithPendingContribution();
    $recurId = (int) $bag->toArray()['recurringContributionId'];

    $contributionId = $this->getLatestPendingContributionId($recurId);
    $this->assertGreaterThan(0, $contributionId);

    $paymentsBefore = $this->countFinancialTrxnsForContribution($contributionId);

    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    if (!$result) {
      fwrite(STDERR, "\nWorker failed: " . json_encode(CRM_AssumedPayments_Queue_AssumedPaymentWorker::$lastFail) . "\n");
    }
    $this->assertTrue($result);

    // Payment wurde genau einmal hinzugefügt
    $paymentsAfter = $this->countFinancialTrxnsForContribution($contributionId);
    $this->assertSame($paymentsBefore + 1, $paymentsAfter);

    // Flag wurde gesetzt (über eure Produktiv-Idempotenz-Logik)
    $this->assertTrue($this->assumedFlagExistsForContribution($contributionId));

    $row = civicrm_api3('Contribution', 'getsingle', [
      'id' => $contributionId,
      'return' => ['contribution_status_id'],
    ]);

    $label = (string) civicrm_api3('OptionValue', 'getvalue', [
      'option_group_id' => 'contribution_status',
      'value' => $row['contribution_status_id'],
      'return' => 'name',
    ]);

    $this->assertSame('Completed', $label);
  }

  public function testRun_IsIdempotent_DoesNotCreateSecondPayment(): void {
    $ctx = $this->createQueueContext();
    $bag = ContributionRecurScenario::pendingRecurWithPendingContribution();
    $recurId = (int) $bag->toArray()['recurringContributionId'];

    $contributionId = $this->getLatestPendingContributionId($recurId);
    $this->assertGreaterThan(0, $contributionId);

    $result1 = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    $this->assertTrue($result1);

    $paymentsAfterFirst = $this->countFinancialTrxnsForContribution($contributionId);
    $this->assertGreaterThan(0, $paymentsAfterFirst);

    $result2 = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    $this->assertTrue($result2);

    $paymentsAfterSecond = $this->countFinancialTrxnsForContribution($contributionId);
    $this->assertSame($paymentsAfterFirst, $paymentsAfterSecond, 'Second run must not create another payment');
  }

  public function testRun_WithoutExistingContribution_CreatesContributionAndPayment(): void {
    $ctx = $this->createQueueContext();
    $bag = ContributionRecurScenario::pendingRecurWithoutContribution();
    $recurId = (int) $bag->toArray()['recurringContributionId'];

    $contribBefore = $this->countContributionsForRecur($recurId);

    $result = CRM_AssumedPayments_Queue_AssumedPaymentWorker::run($ctx, ['recur_id' => $recurId]);
    $this->assertTrue($result);

    $contribAfter = $this->countContributionsForRecur($recurId);
    $this->assertSame($contribBefore + 1, $contribAfter, 'Worker must create a new pending contribution instance');

    $contributionId = $this->getLatestContributionIdForRecur($recurId);
    $this->assertGreaterThan(0, $contributionId);

    $this->assertSame(1, $this->countFinancialTrxnsForContribution($contributionId));
    $this->assertTrue($this->assumedFlagExistsForContribution($contributionId));
  }

  /**
   * ---- HELPERS
   */

  /**
   * @return CRM_Queue_TaskContext
   */
  private function createQueueContext(): CRM_Queue_TaskContext {
    $spec = [
      'type' => 'Sql',
      'name' => 'assumed_payments-test',
      'reset' => TRUE,
    ];

    $queue = new CRM_Queue_Queue_Sql($spec);

    return new CRM_Queue_TaskContext($queue);
  }

  private function getLatestPendingContributionId(int $recurId): int {
    try {
      return (int) civicrm_api3('Contribution', 'getvalue', [
        'contribution_recur_id' => $recurId,
        'contribution_status_id' => 'PENDING',
        'return' => 'id',
        'options' => ['sort' => 'id DESC'],
      ]);
    }
    catch (\CRM_Core_Exception $e) {
      return 0;
    }
  }

  private function getLatestContributionIdForRecur(int $recurId): int {
    try {
      return (int) civicrm_api3('Contribution', 'getvalue', [
        'contribution_recur_id' => $recurId,
        'return' => 'id',
        'options' => ['sort' => 'id DESC'],
      ]);
    }
    catch (\CRM_Core_Exception $e) {
      return 0;
    }
  }

  private function countContributionsForRecur(int $recurId): int {
    $res = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $recurId,
    ]);
    return (int) $res;
  }

  private function countFinancialTrxnsForContribution(int $contributionId): int {
    $eft = civicrm_api3('EntityFinancialTrxn', 'get', [
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $contributionId,
      'return' => ['financial_trxn_id'],
      'options' => ['limit' => 0],
    ]);

    if (empty($eft['values']) || !is_array($eft['values'])) {
      return 0;
    }

    $ids = array_unique(array_filter(array_map(
      static fn($v) => (int) ($v['financial_trxn_id'] ?? 0),
      $eft['values']
    )));

    return count($ids);
  }

  /**
   * Prüft, ob irgendeine FinancialTrxn, die an der Contribution hängt,
   * das assumed flag trägt.
   */
  private function assumedFlagExistsForContribution(int $contributionId): bool {
    $groupId = (int) civicrm_api3('CustomGroup', 'getvalue', [
      'name' => 'assumed_payments_financialtrxn',
      'return' => 'id',
    ]);

    $fieldId = (int) civicrm_api3('CustomField', 'getvalue', [
      'custom_group_id' => $groupId,
      'name' => 'is_assumed',
      'return' => 'id',
    ]);
    $customKey = 'custom_' . $fieldId;

    $eft = civicrm_api3('EntityFinancialTrxn', 'get', [
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $contributionId,
      'return' => ['financial_trxn_id'],
      'options' => ['limit' => 0],
    ]);

    if (empty($eft['values'])) {
      return FALSE;
    }

    $trxnIds = array_unique(array_filter(array_map(
      static fn($v) => (int) ($v['financial_trxn_id'] ?? 0),
      $eft['values']
    )));

    if (!$trxnIds) {
      return FALSE;
    }

    $found = civicrm_api3('FinancialTrxn', 'getcount', [
      'id' => ['IN' => $trxnIds],
      $customKey => 1,
    ]);

    return ((int) $found) > 0;
  }

}
