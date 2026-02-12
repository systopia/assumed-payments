<?php

declare(strict_types = 1);

/**
 * Queue worker: creates assumed payments for recurring contributions.
 *
 * Flow:
 * - validate `recur_id` and ensure the recurring contribution exists
 * - retrieve the latest contribution instance for the recur (if any)
 * - if an assumed payment already exists for that contribution, skip (idempotent)
 * - otherwise ensure a contribution instance exists (create if missing)
 * - create a payment (FinancialTrxn via Payment.create) and set the custom flag
 *   `assumedpayments_financialtrxn.is_assumed` on the created transaction
 */
class CRM_AssumedPayments_Queue_AssumedPaymentWorker {

  /**
   * @var ?array<string, mixed> $lastFail
   */
  public static ?array $lastFail = NULL;

  /**
   * Queue task entrypoint.
   *
   * @param \CRM_Queue_TaskContext $ctx
   *   Queue context (provided by the queue runner).
   * @param array<string, mixed> $data
   *   Queue item data, e.g. ['recur_id' => 123].
   *
   * @return bool
   *   TRUE on success, FALSE on skip / validation / known failure.
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function run(CRM_Queue_TaskContext $ctx, array $data): bool {
    self::$lastFail = NULL;

    if (!isset($data['recur_id']) || !is_numeric($data['recur_id'])) {
      return self::fail('the recurring contribution id is invalid.', ['data' => $data]);
    }

    $recurId = (int) $data['recur_id'];
    if (!self::recurExists($recurId)) {
      return self::fail('the recurring contribution does not exist.', ['recurId' => $recurId]);
    }

    $tx = new CRM_Core_Transaction();

    try {

      $contributionId = self::retrieveLatestContributionInstance($recurId);

      if ($contributionId !== NULL) {
        if (self::assumedPaymentExistsForContribution($contributionId)) {
          \Civi::log()->info(
            'AssumedPayments: skipping already assumed contribution.',
            ['contributionId' => $contributionId]
          );
          return FALSE;
        }
        self::markContributionAsCompleted($contributionId);
      }
      else {
        // no contribution exists -> create it
        $contributionId = self::createContributionInstanceFromRecur($recurId);
      }

      // If we reach here, we must create payment + flag
      $amount = self::getContributionAmount($contributionId);
      $trxnId = self::createAssumedPayment($contributionId, $amount);
      self::flagAssumedOnTrxn($trxnId);

      $tx->commit();
      return TRUE;
    }
    //@codeCoverageIgnoreStart
    catch (CRM_Core_Exception $e) {
      $tx->rollback();
      return self::fail($e->getMessage(), $e->getErrorData());
    }
    //@codeCoverageIgnoreEnd
  }

  /**
   * Checks whether the given recurring contribution exists.
   *
   * @param int $recurId
   * @return bool
   */
  private static function recurExists(int $recurId): bool {
    try {
      \Civi\Api4\ContributionRecur::get(FALSE)
        ->addWhere('id', '=', $recurId)
        ->execute()
        ->single();
      return TRUE;
    }
    catch (\CRM_Core_Exception) {
      return FALSE;
    }
  }

  /**
   * Creates a pending contribution instance for a recurring contribution.
   *
   * @param int $recurId
   * @return int
   *   new contribution ID.
   *
   * @throws \CRM_Core_Exception
   */
  private static function createContributionInstanceFromRecur(int $recurId): int {
    try {
      $recur = \Civi\Api4\ContributionRecur::get(FALSE)
        ->addSelect('contact_id', 'amount', 'currency', 'financial_type_id')
        ->addWhere('id', '=', $recurId)
        ->execute()
        ->single();
    }
    //@codeCoverageIgnoreStart
    catch (\CRM_Core_Exception $e) {
      throw new CRM_Core_Exception(
        'Failed to retrieve the recurring contribution.',
        0,
        ['recurId' => $recurId],
        $e
      );
    }
    //@codeCoverageIgnoreEnd

    try {
      $created = \Civi\Api4\Contribution::create(FALSE)
        ->addValue('contact_id', (int) $recur['contact_id'])
        ->addValue('contribution_recur_id', $recurId)
        ->addValue('total_amount', (float) $recur['amount'])
        ->addValue('currency', (string) $recur['currency'])
        ->addValue('financial_type_id', (int) $recur['financial_type_id'])
        ->addValue('contribution_status_id:name', 'Completed')
        ->addValue('source', 'AssumedPayments')
        ->execute()
        ->first();
    }
    //@codeCoverageIgnoreStart
    catch (\CRM_Core_Exception $e) {
      throw new CRM_Core_Exception(
        'Failed to create a contribution for the recurring contribution.',
        0,
        ['recurId' => $recurId],
        $e
      );
    }
    //@codeCoverageIgnoreEnd
    return (int) ($created['id'] ?? 0);
  }

  /**
   * Checks whether the given contribution already has a financial transaction linked.
   *
   * @param int $contributionId
   * @return bool
   *
   * @throws \CRM_Core_Exception
   */
  private static function assumedPaymentExistsForContribution(int $contributionId): bool {
    try {
      $eft = \Civi\Api4\EntityFinancialTrxn::get(FALSE)
        ->addSelect('id')
        ->addWhere('entity_table', '=', 'civicrm_contribution')
        ->addWhere('entity_id', '=', $contributionId)
        ->addWhere('financial_trxn_id.assumedpayments_financialtrxn.is_assumed', '=', TRUE)
        ->setLimit(1)
        ->execute()
        ->first();

      return ($eft !== NULL);
    }
    //@codeCoverageIgnoreStart
    catch (CRM_Core_Exception $e) {
      throw new CRM_Core_Exception(
        'Failed to query existing financial transactions for contribution.',
        0,
        ['contributionId' => $contributionId],
        $e
      );
    }
    //@codeCoverageIgnoreEnd
  }

  /**
   * Reads total_amount from the contribution.
   *
   * @param int $contributionId
   * @return float
   *
   * @throws \CRM_Core_Exception
   */
  private static function getContributionAmount(int $contributionId): float {
    try {
      $result = \Civi\Api4\Contribution::get(FALSE)
        ->addSelect('total_amount')
        ->addWhere('id', '=', $contributionId)
        ->execute()
        ->single();
    }
    //@codeCoverageIgnoreStart
    catch (\CRM_Core_Exception $e) {
      throw new CRM_Core_Exception(
        'Failed to retrieve the contribution.',
        0,
        ['contributionId' => $contributionId],
        $e
      );
    }
    //@codeCoverageIgnoreEnd

    return (float) $result['total_amount'];
  }

  /**
   * Creates a completed payment for the contribution.
   *
   * Uses Payment.create, which creates/links the underlying FinancialTrxn.
   *
   * @param int $contributionId
   * @param float $amount
   * @return int
   *   Financial transaction ID.
   *
   * @throws \CRM_Core_Exception
   */
  private static function createAssumedPayment(int $contributionId, float $amount): int {
    try {
      /** @var \Civi\Api4\Generic\Result $res */
      $res = \Civi\Api4\Payment::create(FALSE)
        ->addValue('contribution_id', $contributionId)
        ->addValue('total_amount', $amount)
        ->addValue('currency', 'EUR')
        ->addValue('trxn_date', date('Y-m-d H:i:s'))
        ->addValue('payment_instrument_id', 1)
        ->addValue('status_id:name', 'Completed')
        //At the moment the Payment Model is not able to save custom values - lets hope for an update
        //->a d d V a l u e('assumedpayments_financialtrxn.assumed_payment', 1)
        ->execute();

      $trxn = $res->single();
    }
    //@codeCoverageIgnoreStart
    catch (\Throwable $e) {
      throw new CRM_Core_Exception(
        'Failed to create the payment for the contribution.',
        0,
        ['contributionId' => $contributionId],
        $e
      );
    }
    //@codeCoverageIgnoreEnd

    return (int) $trxn['id'];
  }

  /**
   * Sets the custom field `assumedpayments_financialtrxn.is_assumed` on a FinancialTrxn.
   *
   * Note: Payment.create currently cannot persist custom values on FinancialTrxn
   * directly, therefore this uses APIv3 CustomValue.create.
   *
   * @param int $trxnId
   *
   * @throws \CRM_Core_Exception
   */
  private static function flagAssumedOnTrxn(int $trxnId): void {
    try {
      $fieldId = (int) \Civi\Api4\CustomField::get(FALSE)
        ->addSelect('id')
        ->addWhere('custom_group_id:name', '=', 'assumedpayments_financialtrxn')
        ->addWhere('name', '=', 'is_assumed')
        ->setLimit(1)
        ->execute()
        ->single()['id'];
    }
    //@codeCoverageIgnoreStart
    catch (\CRM_Core_Exception $e) {
      throw new CRM_Core_Exception(
        'Failed to retrieve the customField for is_assumed.',
        0, [],
        $e
      );
    }
    //@codeCoverageIgnoreEnd
    try {
      civicrm_api3('CustomValue', 'create', [
        'entity_table' => 'FinancialTrxn',
        'entity_id' => $trxnId,
        'custom_' . $fieldId => TRUE,
      ]);
    }
    //@codeCoverageIgnoreStart
    catch (\CRM_Core_Exception $e) {
      throw new CRM_Core_Exception(
        'Failed to create the `is_assumed`-Flag for the transaction.',
        0, [
          'msg' => $e->getMessage(),
          'transactionId' => $trxnId,
          'fieldId' => $fieldId,
        ],
        $e
      );
    }
    //@codeCoverageIgnoreEnd
  }

  /**
   * Marks the given contribution as "Completed".
   *
   * Updates the contribution_status_id to the option value with name "Completed"
   * using APIv4. Throws a CRM_Core_Exception if the update fails.
   *
   * @param int $contributionId
   *   The ID of the contribution to update.
   *
   * @throws \CRM_Core_Exception
   */
  private static function markContributionAsCompleted(int $contributionId): void {
    try {
      \Civi\Api4\Contribution::update(FALSE)
        ->addValue('contribution_status_id:name', 'Completed')
        ->addWhere('id', '=', $contributionId)
        ->execute();
    }
    //@codeCoverageIgnoreStart
    catch (CRM_Core_Exception $e) {
      throw new CRM_Core_Exception(
        'Failed to mark the contribution as completed.',
        0, [
          'msg' => $e->getMessage(),
          'contributionId' => $contributionId,
        ],
        $e
      );
    }
    //@codeCoverageIgnoreEnd
  }

  /**
   * Retrieves the most recent contribution for the recurring contribution.
   *
   * @param int $recurId
   * @return int|null
   *   Contribution ID or NULL if none exists.
   *
   * @throws \CRM_Core_Exception
   */
  private static function retrieveLatestContributionInstance(int $recurId): ?int {
    try {
      $row = \Civi\Api4\Contribution::get(FALSE)
        ->addSelect('id')
        ->addWhere('contribution_recur_id', '=', $recurId)
        ->addOrderBy('id', 'DESC')
        ->setLimit(1)
        ->execute()
        ->first();
    }
    //@codeCoverageIgnoreStart
    catch (CRM_Core_Exception $e) {
      throw new CRM_Core_Exception(
        'Failed to retrieve latest contribution for recur.',
        0, [
          'msg' => $e->getMessage(),
          'recurId' => $recurId,
        ],
        $e
      );
    }
    //@codeCoverageIgnoreEnd

    return ($row !== NULL) ? $row['id'] : NULL;
  }

  /**
   * Records a failure and returns FALSE.
   *
   * @param string $reason
   * @param array<string, mixed> $context
   * @return bool
   */
  private static function fail(string $reason, array $context = []): bool {
    self::$lastFail = ['reason' => $reason, 'context' => $context];
    \Civi::log()->warning('AssumedPayments: FAIL ' . $reason, $context);
    return FALSE;
  }

}
