<?php

declare(strict_types = 1);

class CRM_AssumedPayments_Settings {

  public const DEFAULT_BATCH_SIZE = 500;

  /**
   * Defines the valid contribution status ids for the form
   *
   * @return array<int, string>
   */
  public static function contributionStatusOptions(): array {
    $array = self::contributionStatusOptionsFull();
    $labelToId = array_flip($array);
    unset($labelToId['Completed']);
    unset($labelToId['Template']);
    $idToLabel = array_flip($labelToId);
    ksort($idToLabel);
    return $idToLabel;
  }

  /**
   * Defines the valid contribution status ids for the final state
   *
   * @return array<int, string>
   */
  public static function contributionFinalStatusOptions(): array {
    $array = self::contributionStatusOptionsFull();
    $labelToId = array_flip($array);
    unset($labelToId['Template']);
    $idToLabel = array_flip($labelToId);
    ksort($idToLabel);
    $idToLabel[0] = 'Calculated Default';
    return $idToLabel;
  }

  /**
   * Defines the valid full range of contribution status ids for the final state
   *
   * @return array<int, string>
   */
  public static function contributionStatusOptionsFull(): array {
    return CRM_Core_OptionGroup::values('contribution_status');
  }

  /**
   * Defines the valid payment ids for the form
   *
   * @return array<int, string>
   */
  public static function paymentInstrumentOptions(): array {
    return CRM_Core_OptionGroup::values('payment_instrument');
  }

  /**
   * Defines the valid financial type ids for the form
   *
   * @return array<int, string>
   */
  public static function financialTypeOptions(): array {
    return CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes();
  }

}
