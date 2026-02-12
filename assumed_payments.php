<?php
declare(strict_types = 1);

// phpcs:disable PSR1.Files.SideEffects
require_once __DIR__ . '/assumed_payments.civix.php';
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function assumed_payment_civicrm_config(\CRM_Core_Config $config): void {
  _assumed_payment_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function assumed_payment_civicrm_install(): void {
  _assumed_payment_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function assumed_payment_civicrm_enable(): void {
  _assumed_payment_civix_civicrm_enable();
}
