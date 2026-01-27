<?php

declare(strict_types = 1);

namespace Stubs\CRM\AssumedPayments\Form;

use CRM_AssumedPayments_Form_AssumedPayments;

class AssumedPaymentsStub extends CRM_AssumedPayments_Form_AssumedPayments {

  /**
   * @var array<string,mixed>
   */
  private array $exportValuesStub = [];

  /**
   * Werte setzen, die exportValues() zurückliefern soll.
   *
   * @param array<string,mixed> $values
   */
  public function setExportValuesStub(array $values): void {
    $this->exportValuesStub = $values;
  }

  /**
   * QuickForm-Export simulieren.
   *
   * @inheritDoc
   */
  public function exportValues($elementList = NULL, $filterInternal = FALSE): array {
    return $this->exportValuesStub;
  }

}
