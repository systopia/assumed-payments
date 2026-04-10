<?php

declare(strict_types = 1);

use PHPUnit\Framework\TestCase;

/**
 * @covers CRM_AssumedPayments_Settings
 */
final class CRM_AssumedPayments_SettingsTest extends TestCase {

  public function testContributionStatusOptions_ExcludesCompletedAndIsSortedById(): void {
    $options = CRM_AssumedPayments_Settings::contributionStatusOptions();

    self::assertIsArray($options);
    self::assertNotEmpty($options, 'Options must not be empty');
    self::assertNotContains('Completed', array_values($options), 'Completed must be excluded from id->label options');

    foreach (array_keys($options) as $id) {
      self::assertIsInt($id, 'Option keys must be ints (status IDs)');
      self::assertGreaterThan(0, $id, 'Status IDs must be positive');
    }

    foreach ($options as $label) {
      self::assertIsString($label);
      self::assertNotSame('', $label);
    }

    $ids = array_keys($options);
    $sorted = $ids;
    sort($sorted, SORT_NUMERIC);
    self::assertSame($sorted, $ids, 'Options must be sorted by status ID ascending');
  }

  public function testContributionFinalStatusOptions_IncludesCalculatedDefault(): void {
    $options = CRM_AssumedPayments_Settings::contributionFinalStatusOptions();

    self::assertIsArray($options);
    self::assertNotEmpty($options, 'Options must not be empty');
    self::assertContains(
      'Calculated Default',
      array_values($options),
      'Calculated Default must be included in id->label options'
    );
  }

  public function testContributionStatusOptionsFull_IncludesCompletedAndHasIntKeys(): void {
    $options = CRM_AssumedPayments_Settings::contributionStatusOptionsFull();

    self::assertIsArray($options);
    self::assertNotEmpty($options, 'Options must not be empty');
    self::assertContains('Completed', array_values($options), 'Full options must include Completed');

    foreach (array_keys($options) as $id) {
      self::assertIsInt($id, 'Option keys must be ints (status IDs)');
      self::assertGreaterThan(0, $id);
    }

    foreach ($options as $label) {
      self::assertIsString($label);
      self::assertNotSame('', $label);
    }
  }

  public function testPaymentInstrumentOptions_ReturnsIdToLabelOptions(): void {
    $options = CRM_AssumedPayments_Settings::paymentInstrumentOptions();

    self::assertIsArray($options);
    self::assertNotEmpty($options, 'Payment instrument options must not be empty');

    foreach (array_keys($options) as $id) {
      self::assertIsInt($id, 'Option keys must be ints (payment instrument IDs)');
      self::assertGreaterThan(0, $id);
    }

    foreach ($options as $label) {
      self::assertIsString($label);
      self::assertNotSame('', $label);
    }
  }

  public function testFinancialTypeOptions_ReturnsAvailableFinancialTypes(): void {
    $options = CRM_AssumedPayments_Settings::financialTypeOptions();

    self::assertIsArray($options);
    self::assertNotEmpty($options, 'Financial type options must not be empty');

    foreach (array_keys($options) as $id) {
      self::assertIsInt($id, 'Option keys must be ints (financial type IDs)');
      self::assertGreaterThan(0, $id);
    }

    foreach ($options as $label) {
      self::assertIsString($label);
      self::assertNotSame('', $label);
    }
  }

}
