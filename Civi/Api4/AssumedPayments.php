<?php

declare(strict_types = 1);

namespace Civi\Api4;

use Civi\Api4\Generic\AbstractEntity;
use Civi\AssumedPayments\Api4\Action\AssumedPayments\GetFields;
use Civi\AssumedPayments\Api4\Action\AssumedPayments\RunJob;
use Civi\AssumedPayments\Api4\Action\AssumedPayments\Schedule;

/**
 * APIv4 entity AssumedPayments.
 *
 * This entity exposes APIv4 actions related to assumed payments for recurring
 * contributions. It does not represent a database-backed entity, but serves as
 * an action-only API namespace.
 */
class AssumedPayments extends AbstractEntity {

  /**
   * Returns the field definitions for the AssumedPayments API.
   *
   * @return \Civi\AssumedPayments\Api4\Action\AssumedPayments\GetFields
   */
  public static function getFields(): GetFields {
    return new GetFields();
  }

  /**
   * The Schedule action identifies eligible recurring contributions and enqueues
   * them for processing.
   *
   * @return \Civi\AssumedPayments\Api4\Action\AssumedPayments\Schedule
   */
  public static function schedule(): Schedule {
    return new Schedule();
  }

  /**
   * The RunJob action executes scheduled assumed payment tasks, typically invoked
   * by a scheduled job or APIv3 wrapper.
   *
   * @return \Civi\AssumedPayments\Api4\Action\AssumedPayments\RunJob
   */
  public static function runJob(): RunJob {
    return new RunJob(static::getEntityName(), 'runJob');
  }

}
