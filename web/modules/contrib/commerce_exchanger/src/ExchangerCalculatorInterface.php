<?php

namespace Drupal\commerce_exchanger;

use Drupal\commerce_price\Price;

/**
 * Provides default price calculator with exchange rates.
 *
 * @package Drupal\commerce_exchanger
 */
interface ExchangerCalculatorInterface {

  /**
   * Preform currency conversion for prices.
   *
   * @param \Drupal\commerce_price\Price $price
   *   Price object.
   * @param string $target_currency
   *   Target currency.
   *
   * @return \Drupal\commerce_price\Price
   *   Return updated price object with new currency.
   */
  public function priceConversion(Price $price, string $target_currency);

  /**
   * Get all exchange rates.
   *
   * @return array
   *   Return exchange rates which are used for calculations.
   */
  public function getExchangeRates();

  /**
   * Return configuration file of active provider or NULL.
   *
   * @return string|null
   *   Return provider.
   */
  public function getExchangerId();

}
