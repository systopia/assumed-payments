<?php

declare(strict_types = 1);

namespace Civi\Api4;

use Civi\Api4\Generic\AbstractEntity;
use Civi\AssumedPayments\Api4\Action\AssumedPayments\GetFields;
use Civi\AssumedPayments\Api4\Action\AssumedPayments\Schedule;

class AssumedPayments extends AbstractEntity {

  public static function getFields(): GetFields {
    return new GetFields();
  }

  public static function schedule(): Schedule {
    return new Schedule();
  }

}
