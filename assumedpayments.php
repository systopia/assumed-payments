<?php
declare(strict_types = 1);

// phpcs:disable PSR1.Files.SideEffects
require_once 'assumedpayments.civix.php';
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function assumedpayments_civicrm_config(\CRM_Core_Config $config): void {
  _assumedpayments_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function assumedpayments_civicrm_install(): void {
  _assumedpayments_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function assumedpayments_civicrm_enable(): void {
  _assumedpayments_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_alterSettingsMetaData().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsMetaData/
 */
function assumedpayments_civicrm_alterSettingsMetaData(array &$settingsMetadata): void {
  $extra = include __DIR__ . '/settings/assumedpayments.setting.php';
  $settingsMetadata = array_merge($settingsMetadata, $extra);
}
