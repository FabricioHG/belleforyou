<?php

namespace Drupal\commerce_exchanger;

/**
 * Represents remote exchange rates data with base currency.
 *
 * @package Drupal\commerce_exchanger
 */
class ExchangerProviderRates {

  /**
   * The base currency.
   *
   * @var string
   */
  protected $baseCurrency;

  /**
   * The provided rates from external provider.
   *
   * @var array
   */
  protected $rates;

  /**
   * The list of enabled currencies.
   *
   * @var array
   */
  protected $currencies;

  /**
   * Determine if transform prices is needed.
   *
   * @var bool
   */
  protected $transform;

  /**
   * Constructs a new ExchangerProviderRates instance.
   *
   * @param array $definition
   *   The definition.
   */
  public function __construct(array $definition) {
    foreach (['base'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new \InvalidArgumentException(sprintf('Missing required property "%s".', $required_property));
      }
    }

    if (!is_array($definition['rates'])) {
      throw new \InvalidArgumentException('The property "rates" must be an array.');
    }

    $this->currencies = $definition['currencies'] ?? NULL;
    $this->baseCurrency = $definition['base'];
    $this->transform = $definition['transform'] ?? FALSE;

    if ($this->currencies && !isset($this->currencies[$this->baseCurrency])) {
      throw new \InvalidArgumentException('The base currency need to be among enabled one in Drupal Commerce');
    }

    foreach ($definition['rates'] as $currency => $rate) {
      if (!is_string($currency)) {
        throw new \InvalidArgumentException('The currency code must be an string.');
      }

      if (!is_numeric($rate)) {
        throw new \InvalidArgumentException('The rate must be an float or a numeric string.');
      }

      // Filter data.
      if ($this->currencies && !isset($this->currencies[$currency])) {
        unset($definition['rates'][$currency]);
        continue;
      }

      $definition['rates'][$currency] = round($this->transform ? 1 / $rate: $rate, 6);
    }

    $this->rates = $definition['rates'];
  }

  /**
   * Get base currency upon rates are built.
   *
   * @return string
   *   Return currency ISO code.
   */
  public function getBaseCurrency() {
    return $this->baseCurrency;
  }

  /**
   * List all rate values keyed by currency code.
   *
   * @return array
   *   Keyed array by currency code, and value for rate. ['HRK' => '0.5']
   */
  public function getRates() {
    return $this->rates;
  }

  /**
   * Determine if price where transformed.
   *
   * @return bool
   *   Return true if price where transformed.
   */
  public function isTransform() {
    return $this->transform;
  }

  /**
   * List enabled currencies.
   *
   * @return array
   *   Return keyed currencies by currency code.
   */
  public function getCurrencies() {
    return $this->currencies;
  }

}
