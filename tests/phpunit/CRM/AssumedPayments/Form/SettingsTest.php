<?php
declare(strict_types = 1);

use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests für alle AssumedPayments Settings.
 * @covers CRM_AssumedPayments_Form_AssumedPayments
 *
 * @group headless
 */
// phpcs:ignore Generic.Files.LineLength.TooLong
class CRM_AssumedPayments_AssumedPaymentsSettingsTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * Reset Settings on each test.
   * @return void
   */
  public function setUp(): void {
    parent::setUp();
    $s = Civi::settings();
    $s->set('assumedpayments_date_from', NULL);
    $s->set('assumedpayments_date_to', NULL);
    $s->set('assumedpayments_contribution_status_ids', []);
    $s->set('assumedpayments_batch_size', NULL);
    $s->set('assumedpayments_dry_run_default', NULL);
  }

  //--- HELPERS

  /**
   * Calls the protected save method.
   */
  private function callSaveSettings(array $values): void {
    $form = new CRM_AssumedPayments_Form_AssumedPayments();
    $ref = new ReflectionClass($form);
    $m = $ref->getMethod('saveSettings');
    $m->setAccessible(TRUE);
    $m->invoke($form, $values);
  }

  private function getDefaults(): array {
    $form = new CRM_AssumedPayments_Form_AssumedPayments();
    return $form->setDefaultValues();
  }

  /**
   * ---- FORM TEST
   */
  public function testBuildQuickForm_CreatesExpectedElements(): void {
    $form = new CRM_AssumedPayments_Form_AssumedPayments();
    $form->buildQuickForm();
    $names = $form->getRenderableElementNames();
    $known = implode(', ', $names);

    self::assertTrue(
      $form->elementExists('assumedpayments_date_low'),
      'Expected element "assumedpayments_date_from" not found. Known elements: ' . $known
    );
    self::assertTrue(
      $form->elementExists('assumedpayments_date_high'),
      'Expected element "assumedpayments_date_to" not found. Known elements: ' . $known
    );
    self::assertTrue(
      $form->elementExists('assumedpayments_contribution_status_ids'),
      'Expected element "assumedpayments_contribution_status_ids" not found. Known elements: ' . $known
    );
    self::assertTrue(
      $form->elementExists('assumedpayments_batch_size'),
      'Expected element "assumedpayments_batch_size" not found. Known elements: ' . $known
    );
    self::assertTrue(
      $form->elementExists('assumedpayments_dry_run_default'),
      'Expected element "assumedpayments_dry_run_default" not found. Known elements: ' . $known
    );
  }

  /**
   * ---- SETTING TESTS (DATE RANGE)
   */
  public function testSetDateRange_WithValidValues_SavesNormalizedDates(): void {
    $this->callSaveSettings([
      'assumedpayments_date_from' => '2025-01-02',
      'assumedpayments_date_to' => '2025-01-31',
    ]);

    $defaults = $this->getDefaults();
    self::assertSame('2025-01-02', $defaults['assumedpayments_date_from']);
    self::assertSame('2025-01-31', $defaults['assumedpayments_date_to']);
  }

  public function testSetDateRange_WithDateTimeValues_NormalizesToDateOnly(): void {
    $this->callSaveSettings([
      'assumedpayments_date_from' => '2025-01-02 10:11:12',
      'assumedpayments_date_to' => '2025-01-31 23:59:59',
    ]);

    $defaults = $this->getDefaults();
    self::assertSame('2025-01-02', $defaults['assumedpayments_date_from']);
    self::assertSame('2025-01-31', $defaults['assumedpayments_date_to']);
  }

  public function testSetDateRange_WithoutKeys_UsesNullDefaults(): void {
    $this->callSaveSettings([]);

    $defaults = $this->getDefaults();
    self::assertNull($defaults['assumedpayments_date_from']);
    self::assertNull($defaults['assumedpayments_date_to']);
  }

  public function testSetDateRange_WithInvalidValues_StoresNull(): void {
    $this->callSaveSettings([
      'assumedpayments_date_from' => '__INVALID__',
      'assumedpayments_date_to' => '__INVALID__',
    ]);

    $defaults = $this->getDefaults();
    self::assertNull($defaults['assumedpayments_date_from']);
    self::assertNull($defaults['assumedpayments_date_to']);
  }

  public function testSetDateRange_WithNullValues_StoresNull(): void {
    $this->callSaveSettings([
      'assumedpayments_date_from' => NULL,
      'assumedpayments_date_to' => NULL,
    ]);

    $defaults = $this->getDefaults();
    self::assertNull($defaults['assumedpayments_date_from']);
    self::assertNull($defaults['assumedpayments_date_to']);
  }

  /**
   * ---- OTHER SETTING TESTS (unchanged)
   */
  public function testSetRecurStatusIds_WithValidEntries_SavesCorrectValues(): void {
    $this->callSaveSettings([
      'assumedpayments_contribution_status_ids' => [
        2 => 1,
        3 => 1,
        4 => 1,
        7 => 1,
      ],
    ]);
    $defaults = $this->getDefaults();
    self::assertSame(
      [2 => 1, 3 => 1, 4 => 1, 7 => 1],
      $defaults['assumedpayments_contribution_status_ids']
    );
  }

  public function testSetRecurStatusIds_WithInvalidEntries_SavesEmptyArray(): void {
    $this->callSaveSettings([
      'assumedpayments_contribution_status_ids' => [
        1 => 0,
        2 => 0,
        9999 => 1,
      ],
    ]);
    $defaults = $this->getDefaults();
    self::assertSame([], $defaults['assumedpayments_contribution_status_ids']);
  }

  public function testSetBatchSize_WithValidValue_SavesCorrectValue(): void {
    $default = 250;
    $this->callSaveSettings([
      'assumedpayments_batch_size' => $default,
    ]);
    $defaults = $this->getDefaults();
    self::assertSame($default, $defaults['assumedpayments_batch_size']);
  }

  public function testSetBatchSize_WithInvalidValue_UsesDefaultValue(): void {
    $this->callSaveSettings([
      'assumedpayments_batch_size' => '__INVALID__',
    ]);
    $defaults = $this->getDefaults();
    self::assertSame(
      CRM_AssumedPayments_Form_AssumedPayments::BATCH_SIZE_DEFAULT,
      $defaults['assumedpayments_batch_size']
    );
  }

  public function testSetBatchSize_WithEmptyString_UsesDefaultValue(): void {
    $this->callSaveSettings([
      'assumedpayments_batch_size' => '',
    ]);
    $defaults = $this->getDefaults();
    self::assertSame(
      CRM_AssumedPayments_Form_AssumedPayments::BATCH_SIZE_DEFAULT,
      $defaults['assumedpayments_batch_size']
    );
  }

  public function testSetDryRun_WithValidValue_UsesCorrectValue(): void {
    $default = 0;
    $this->callSaveSettings([
      'assumedpayments_dry_run_default' => $default,
    ]);
    $defaults = $this->getDefaults();
    self::assertSame($default, $defaults['assumedpayments_dry_run_default']);
  }

  public function testSetDryRun_WithoutKey_UsesDefaultValue(): void {
    $this->callSaveSettings([]);
    $defaults = $this->getDefaults();
    self::assertSame(1, $defaults['assumedpayments_dry_run_default']);
  }

  public function testSetDryRun_WithInvalidValue_UsesDefaultValue(): void {
    $this->callSaveSettings([
      'assumedpayments_dry_run_default' => '__INVALID__',
    ]);
    $defaults = $this->getDefaults();
    self::assertSame(1, $defaults['assumedpayments_dry_run_default']);
  }

  public function testSetDryRun_WithNullValue_UsesDefaultValue(): void {
    $this->callSaveSettings([
      'assumedpayments_dry_run_default' => NULL,
    ]);
    $defaults = $this->getDefaults();
    self::assertSame(1, $defaults['assumedpayments_dry_run_default']);
  }

  public function testPostProcess_SavesSettingsCorrectly(): void {
    $statusOptions = CRM_Core_OptionGroup::values('contribution_status', TRUE);
    unset($statusOptions['Completed']);
    $validStatusIds = array_values($statusOptions);
    $statusId = reset($validStatusIds);

    $form = new Stubs\CRM\AssumedPayments\Form\AssumedPaymentsStub();

    $form->setExportValuesStub([
      'assumedpayments_date_from' => '2025-01-02',
      'assumedpayments_date_to' => '2025-01-31',
      'assumedpayments_contribution_status_ids' => [
        $statusId => 2,
      ],
      'assumedpayments_batch_size' => 250,
      'assumedpayments_dry_run_default' => 0,
    ]);

    $form->postProcess();
    $defaults = $this->getDefaults();

    self::assertSame('2025-01-02', $defaults['assumedpayments_date_from']);
    self::assertSame('2025-01-31', $defaults['assumedpayments_date_to']);

    self::assertArrayHasKey($statusId, $defaults['assumedpayments_contribution_status_ids']);
    self::assertSame(250, $defaults['assumedpayments_batch_size']);
    self::assertSame(0, $defaults['assumedpayments_dry_run_default']);
  }

}
