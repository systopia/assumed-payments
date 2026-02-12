<?php

declare(strict_types = 1);

class CRM_AssumedPayments_Settings {

  /**
   * Defines the valid status ids for the form
   */
  public static function contributionStatusOptions(): array {
    $labelToId = CRM_Core_OptionGroup::values('contribution_status', TRUE);
    unset($labelToId['Completed']);
    $idToLabel = array_flip($labelToId);
    ksort($idToLabel);
    return $idToLabel;
  }

}
