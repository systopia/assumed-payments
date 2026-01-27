<?php

declare(strict_types = 1);

class CRM_AssumedPayments_Queue_AssumedPaymentWorker {

  private static ?int $assumedCustomFieldId = NULL;
  public static ?array $lastFail = NULL;

  /**
   * @param CRM_Queue_TaskContext $ctx
   * @param array $data e.g. ['recur_id' => 123]
   * @return bool
   * @throws CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function run(CRM_Queue_TaskContext $ctx, array $data): bool {
    self::$lastFail = NULL;

    $recurId = (int) ($data['recur_id'] ?? 0);
    if ($recurId <= 0) {
      return self::fail('invalid_recur_id', ['data' => $data]);
    }

    if (!self::recurExists($recurId)) {
      return self::fail('recur_not_found', ['recur_id' => $recurId]);
    }

    $contributionId = self::getOrCreatePendingContributionInstance($recurId);
    if ($contributionId <= 0) {
      return self::fail('no_contribution_instance', ['recur_id' => $recurId]);
    }

    if (self::assumedPaymentExistsForContribution($contributionId)) {
      \Civi::log()->info('AssumedPayments: already assumed, skip', ['contribution_id' => $contributionId]);
      return TRUE;
    }

    $amount = self::getContributionAmount($contributionId);
    self::createAssumedPayment($contributionId, $amount);
    self::flagAssumedOnLatestTrxnForContribution($contributionId);
    return TRUE;
  }

  private static function recurExists(int $recurId): bool {
    $recur = \Civi\Api4\ContributionRecur::get(FALSE)->addWhere('id', '=', $recurId)->execute()->first();
    if (!$recur) {
      \Civi::log()->warning('AssumedPayments: ContributionRecur not found', ['recur_id' => $recurId]);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Try to find an existing pending contribution instance for this recur.
   */
  private static function getOrCreatePendingContributionInstance(int $recurId): int {
    $existingId = 0;
    try {
      $existingId = (int) civicrm_api3(
        'Contribution',
        'getvalue',
        [
          'contribution_recur_id' => $recurId,
          'contribution_status_id' => 'Pending',
          'return' => 'id',
          'options' => ['sort' => 'id DESC'],
        ]
      );
    }
    catch (\CRM_Core_Exception $e) {
      $existingId = 0;
    }
    if ($existingId > 0) {
      return $existingId;
    }
    return self::createContributionInstanceFromRecur($recurId);
  }

  /**
   * Creates a contribution instance for the recur.
   * Important: This creates NO payment/transaction. Payment comes afterwards via Payment.create.
   */
  private static function createContributionInstanceFromRecur(int $recurId): int {
    $receiveDate = date('Y-m-d H:i:s');

    $recur = \Civi\Api4\ContributionRecur::get(FALSE)
      ->addSelect('contact_id', 'amount', 'currency', 'financial_type_id')
      ->addWhere('id', '=', $recurId)
      ->execute()
      ->first();

    if (!$recur) {
      return 0;
    }

    $created = civicrm_api3('Contribution', 'create', [
      'contact_id' => (int) $recur['contact_id'],
      'contribution_recur_id' => $recurId,
      'total_amount' => (float) $recur['amount'],
      'currency' => (string) $recur['currency'],
      'financial_type_id' => (int) $recur['financial_type_id'],
      'receive_date' => $receiveDate,
      'contribution_status_id' => 'Pending',
      'source' => 'AssumedPayments',
    ]);

    return (int) ($created['id'] ?? 0);
  }

  private static function getAssumedCustomFieldId(): int {
    if (self::$assumedCustomFieldId !== NULL) {
      return self::$assumedCustomFieldId;
    }

    $group = civicrm_api3('CustomGroup', 'get', [
      'name' => 'assumedpayments_financialtrxn',
      'return' => ['id'],
      'options' => ['limit' => 1],
    ]);
    $groupId = !empty($group['id']) ? (int) $group['id'] : 0;
    if ($groupId <= 0) {
      throw new CRM_Core_Exception('AssumedPayments: CustomGroup assumedpayments not found.');
    }

    $field = civicrm_api3('CustomField', 'get', [
      'custom_group_id' => $groupId,
      'name' => 'is_assumed',
      'return' => ['id'],
      'options' => ['limit' => 1],
    ]);
    $fieldId = !empty($field['id']) ? (int) $field['id'] : 0;
    if ($fieldId <= 0) {
      throw new CRM_Core_Exception('AssumedPayments: CustomField assumed_payment not found.');
    }

    self::$assumedCustomFieldId = $fieldId;
    return $fieldId;
  }

  private static function assumedPaymentExistsForContribution(int $contributionId): bool {
    $fieldId = self::getAssumedCustomFieldId();
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
      fn($v) => (int) $v['financial_trxn_id'],
      $eft['values']
    )));

    if (!$trxnIds) {
      return FALSE;
    }

    $found = civicrm_api3('FinancialTrxn', 'get', [
      'id' => ['IN' => $trxnIds],
      $customKey => 1,
      'options' => ['limit' => 1],
      'return' => ['id'],
    ]);

    return !empty($found['count']);
  }

  private static function getContributionAmount(int $contributionId): float {
    return (float) civicrm_api3('Contribution', 'getvalue', [
      'id' => $contributionId,
      'return' => 'total_amount',
    ]);
  }

  private static function createAssumedPayment(int $contributionId, float $amount): void {
    $params = [
      'contribution_id' => $contributionId,
      'total_amount' => $amount,
      'trxn_date' => date('Y-m-d H:i:s'),
      'payment_instrument_id' => 1,
    ];

    civicrm_api3('Payment', 'create', $params);
  }

  private static function flagAssumedOnLatestTrxnForContribution(int $contributionId): void {
    $cfid = self::getAssumedCustomFieldId();
    $customKey = 'custom_' . $cfid;

    $eft = civicrm_api3('EntityFinancialTrxn', 'get', [
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $contributionId,
      'return' => ['financial_trxn_id', 'id'],
      'options' => ['limit' => 0, 'sort' => 'id DESC'],
    ]);

    if (empty($eft['values'])) {
      throw new CRM_Core_Exception('AssumedPayments: No EntityFinancialTrxn found for contribution ' . $contributionId);

    }

    $first = reset($eft['values']);
    $trxnId = (int) ($first['financial_trxn_id'] ?? 0);

    if ($trxnId <= 0) {
      throw new CRM_Core_Exception('AssumedPayments: Missing financial_trxn_id for contribution ' . $contributionId);
    }

    civicrm_api3('CustomValue', 'create', [
      'entity_table' => 'FinancialTrxn',
      'entity_id' => $trxnId,
      $customKey => 1,
    ]);

  }

  private static function fail(string $reason, array $context = []): bool {
    self::$lastFail = ['reason' => $reason, 'context' => $context];
    \Civi::log()->warning('AssumedPayments: FAIL ' . $reason, $context);
    return FALSE;
  }

}
