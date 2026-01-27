<?php
declare(strict_types = 1);

use CRM_AssumedPayments_ExtensionUtil as E;

/**
 * Settings form for assumed payments.
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_AssumedPayments_Form_AssumedPayments extends CRM_Core_Form {
  public const BATCH_SIZE_DEFAULT = 500;

  /**
   * Builds the form with the intended settings.
   */
  public function buildQuickForm(): void {
    $this->setTitle(E::ts('Assumed Payments Settings'));

    // Date Range (absolute)
    CRM_Core_Form::addDatePickerRange(
      'assumedpayments_date',
      E::ts('Date range'),
    );

    //Status Ids
    $statusOptions = $this->returnValidStatusIds();
    $this->addCheckBox(
      'assumedpayments_contribution_status_ids',
      E::ts('Contribution Statuses considered open'),
      $statusOptions
    );

    //Batch Size
    $this->add(
      'text',
      'assumedpayments_batch_size',
      E::ts('Batch Size per Run'),
      ['size' => 5],
      TRUE
    );

    //Dry Run
    $this->add(
      'advcheckbox',
      'assumedpayments_dry_run_default',
      E::ts('Dry Run enabled by default')
    );

    //Buttons
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
      ],
    ]);

    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  /**
   * Sets the default values for the form entries.
   * @return array<string, mixed> - Array of default values for the form fields.
   */
  public function setDefaultValues(): array {
    $settings = Civi::settings();
    $defaults = [];

    // Date Range
    $defaults['assumedpayments_date_from'] = $this->normalizeDate(
      $settings->get('assumedpayments_date_from')
    );
    $defaults['assumedpayments_date_to'] = $this->normalizeDate(
      $settings->get('assumedpayments_date_to')
    );

    //Status Ids
    /** @var list<int> $storedStatusIds */
    $storedStatusIds = (array) $settings->get('assumedpayments_contribution_status_ids');
    $validStatusIds = $this->normalizeStatusIds($storedStatusIds);
    $defaults['assumedpayments_contribution_status_ids'] = array_fill_keys($validStatusIds, 1);

    //Batch Size
    $storedBatchSize = $settings->get('assumedpayments_batch_size');
    $defaults['assumedpayments_batch_size'] = $this->normalizeBatchSize($storedBatchSize);

    //Dry Run
    $storedDryRun = $settings->get('assumedpayments_dry_run_default');
    $defaults['assumedpayments_dry_run_default'] =
      intval($this->normalizeDryRun($storedDryRun));

    return $defaults;
  }

  /**
   * Form procession of the valid form.
   */
  public function postProcess(): void {
    $values = $this->exportValues();
    $this->saveSettings($values);

    CRM_Core_Session::setStatus(
      E::ts('Assumed Payments settings have been saved.'),
      E::ts('Saved'),
      'success'
    );

    parent::postProcess();
  }

  /**
   * Encapsulates the logic to be testable.
   * @param array<string, mixed> $values
   *   submitted form values
   */
  protected function saveSettings(array $values): void {
    $settings = Civi::settings();

    //Relative Filter
    // Date Range
    $settings->set(
      'assumedpayments_date_from',
      $this->normalizeDate($values['assumedpayments_date_from'] ?? NULL)
    );
    $settings->set(
      'assumedpayments_date_to',
      $this->normalizeDate($values['assumedpayments_date_to'] ?? NULL)
    );

    //Status Ids
    $rawIds = $values['assumedpayments_contribution_status_ids'] ?? [];
    $rawIds = is_array($rawIds) ? $rawIds : [];
    $selectedIds = array_keys(
      array_filter(
        $rawIds,
        static fn($value): bool => (bool) $value
      )
    );
    $settings->set(
      'assumedpayments_contribution_status_ids',
      $this->normalizeStatusIds($selectedIds)
    );

    //Batch Size
    $settings->set(
      'assumedpayments_batch_size',
      $this->normalizeBatchSize($values['assumedpayments_batch_size'] ?? NULL)
    );

    //Dry Run
    $settings->set(
      'assumedpayments_dry_run_default',
      intval($this->normalizeDryRun($values['assumedpayments_dry_run_default'] ?? NULL))
    );
  }

  /**
   * Get the fields/elements defined in this form.
   * @return array<int,string>
   */
  public function getRenderableElementNames(): array {
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_element $element */
      $label = $element->getLabel();
      if ($this->labelIsRenderable($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  /**
   * ---- HELPERS
   */
  private function returnValidStatusIds(): array {
    $statusOptions = CRM_Core_OptionGroup::values('contribution_status', TRUE);
    unset($statusOptions['Completed']);
    return $statusOptions;
  }

  /**
   * Robust check avoiding empty.
   * @param mixed $label
   *   the elements label
   */
  private function labelIsRenderable($label): bool {
    if ($label === NULL) {
      return FALSE;
    }

    if (is_string($label)) {
      return trim($label) !== '';
    }

    if (is_array($label)) {
      return count($label) > 0;
    }
    return FALSE;
  }

  /**
   * Normalizes a date input to YYYY-MM-DD or NULL.
   *
   * @param mixed $value
   * @return string|null
   */
  private function normalizeDate($value): ?string {
    if ($value === NULL || $value === '') {
      return NULL;
    }

    try {
      // Accepts various inputs; normalizes to date-only.
      $dt = new \DateTime((string) $value);
      return $dt->format('Y-m-d');
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Responsible for returning valid status ids.
   * @param list<int> $ids
   * @return list<int>
   *   Array of valid recur_status_ids in format [4 => 1, 7 => 1, ...]
   */
  private function normalizeStatusIds(array $ids): array {
    $statusOptions = $this->returnValidStatusIds();
    $validStatusIds = array_values($statusOptions);
    $ids = array_map(static fn($value): int => $value, $ids);
    return array_values(array_intersect($ids, $validStatusIds));
  }

  /**
   * Responsible for returning a valid batch size.
   * @param mixed $value
   *   the given batch size
   * @return int
   *   the valid batch size
   */
  private function normalizeBatchSize($value): int {
    $default = self::BATCH_SIZE_DEFAULT;
    if ($value === NULL || $value === '') {
      return $default;
    }
    $int = (int) $value;
    if ($int <= 0) {
      return $default;
    }
    return $int;
  }

  /**
   * Responsible for returning a valid dry run state.
   * @param int|null|string $value
   *   the given state
   * @return bool
   *   true if dry run, false if not
   */
  private function normalizeDryRun($value): bool {
    if ($value === 0 || $value === '0') {
      return FALSE;
    }
    return TRUE;
  }

}
