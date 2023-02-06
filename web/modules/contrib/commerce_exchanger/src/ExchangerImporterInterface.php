<?php

namespace Drupal\commerce_exchanger;

/**
 * Interface ExchangerImporterInterface.
 *
 * @package Drupal\commerce_exchanger
 */
interface ExchangerImporterInterface {

  /**
   * Triggers importing exchange rates.
   *
   * @param bool $force
   *   If import is forced regardless of cron setup.
   */
  public function run($force);

}
