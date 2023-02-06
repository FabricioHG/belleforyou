<?php

namespace Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider;

use Drupal\commerce_exchanger\ExchangerProviderRates;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Base class for Commerce exchanger provider plugins.
 */
abstract class ExchangerProviderRemoteBase extends ExchangerProviderBase implements ExchangerProviderRemoteInterface {

  /**
   * {@inheritdoc}
   */
  public function getMethod() {
    return $this->pluginDefinition['method'] ?? 'GET';
  }

  /**
   * {@inheritdoc}
   */
  public function isEnterprise() {
    return $this->configuration['enterprise'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiKey() {
    return $this->configuration['api_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthData() {
    return $this->configuration['auth'];
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseCurrency() {
    return $this->configuration['base_currency'];
  }

  /**
   * {@inheritdoc}
   */
  public function useCrossSync() {
    return $this->configuration['use_cross_sync'];
  }

  /**
   * {@inheritdoc}
   */
  public function transformRates() {
    return $this->pluginDefinition['transform_rates'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function apiClient(array $options) {
    $data = [];

    // Prepare for client.
    $client = $this->httpClientFactory->fromOptions();

    try {
      $response = $client->request($this->getMethod(), $this->apiUrl(), $options);

      // Expected result.
      $data = $response->getBody()->getContents();

    }
    catch (GuzzleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $data;
  }

  /**
   * Process all currencies for rates for other currencies.
   *
   * @return array
   *   Return prepared data for saving.
   */
  protected function buildExchangeRates() {
    // If we use enterprise and we don't want cross sync feature.
    if ($this->isEnterprise() && !$this->useCrossSync()) {
      return $this->importEnterprise();
    }

    // Everything else.
    return $this->importCrossSync();
  }

  /**
   * Preform cross conversion between currencies to build exchange data rates.
   *
   * @return array
   *   Return array of exchange rates.
   */
  protected function importCrossSync() {
    $exchange_rates_data = $this->processRemoteData();
    // Based on cross sync settings fetch and process data.
    return $this->crossSyncCalculate($exchange_rates_data);
  }

  /**
   * Fetch remote provider by each currency and create dataset.
   *
   * @return array
   *   Return array of exchange rates.
   */
  protected function importEnterprise() {
    $exchange_rates = [];
    foreach ($this->currencies as $code => $currency) {
      $exchange_rates_data = $this->processRemoteData($code);
      $exchange_rates += $this->mapExchangeRates($exchange_rates_data);
    }
    return $exchange_rates;
  }

  /**
   * Process data with checking structure and preparing data for importing.
   *
   * @param string|null $base_currency
   *   The base currency or null if none.
   *
   * @return \Drupal\commerce_exchanger\ExchangerProviderRates
   *   The ExchangeRates.
   */
  protected function processRemoteData(string $base_currency = NULL) {
    $remote_data = $this->getRemoteData($base_currency);

    // Validate and build structure.
    if (!isset($remote_data['base'], $remote_data['rates'])) {
      $exchange_rates['rates'] = $remote_data ?? [];
      $exchange_rates['base'] = $base_currency ?? $this->getBaseCurrency();
    }
    else {
      $exchange_rates = $remote_data;
    }

    // Pass enabled currencies to automatically filter data.
    $exchange_rates['currencies'] = $this->getCurrencies();
    $exchange_rates['transform'] = $this->transformRates();
    return new ExchangerProviderRates($exchange_rates);
  }

  /**
   * Rates calculation for currencies when we use cross sync conversion.
   *
   * @param \Drupal\commerce_exchanger\ExchangerProviderRates $exchange_rates
   *   The ExchangeRates.
   *
   * @return array
   *   Return data prepared for saving.
   */
  protected function crossSyncCalculate(ExchangerProviderRates $exchange_rates) {
    $calculated_rates = [];

    // Enabled currency.
    $currencies = $this->currencies;

    foreach ($currencies as $currency_code => $name) {
      $calculate_rates = $this->recalculateRates($currency_code, $exchange_rates);
      $map_rates = $this->mapExchangeRates($calculate_rates);
      $calculated_rates[$currency_code] = $map_rates[$currency_code];
    }

    return $calculated_rates;
  }

  /**
   * Helper function to create array for exchange rates.
   *
   * @param \Drupal\commerce_exchanger\ExchangerProviderRates $exchange_rates
   *   The ExchangeRates.
   *
   * @return array
   *   Return array prepared for saving in Drupal config.
   */
  protected function mapExchangeRates(ExchangerProviderRates $exchange_rates) {
    // Get current exchange rates.
    $mapping = $this->configFactory->get($this->getConfigName())->getRawData();

    $rates = $exchange_rates->getRates();
    $base_currency = $exchange_rates->getBaseCurrency();

    // Set defaults.
    $calculated_rates = [];
    $calculated_rates[$base_currency] = [];

    // Loop trough data, set new values or leave manually defined.
    foreach ($rates as $currency => $rate) {
      // Skip base currency to map to itself.
      if ($currency !== $base_currency) {
        if (empty($mapping[$base_currency][$currency]['sync'])) {
          $calculated_rates[$base_currency][$currency]['value'] = $rate;
          $calculated_rates[$base_currency][$currency]['sync'] = $mapping[$base_currency][$currency]['sync'] ?? 0;
        }
        else {
          $calculated_rates[$base_currency][$currency] = $mapping[$base_currency][$currency];
        }
      }
    }

    return $calculated_rates;
  }

  /**
   * Recalculate currencies from exchange rate between two other currencies.
   *
   * @param string $target_currency
   *   Currency to which should be exchange rate calculated.
   * @param \Drupal\commerce_exchanger\ExchangerProviderRates $data
   *   Currency and rate array.
   *
   * @return \Drupal\commerce_exchanger\ExchangerProviderRates
   *   Return recalculated data.
   */
  protected function recalculateRates(string $target_currency, ExchangerProviderRates $data) {
    $rates = $data->getRates();
    $base_currency = $data->getBaseCurrency();

    // If we accidentally sent same target and base currency.
    $rate_target_currency = $rates[$target_currency] ?? 1;

    // Get rate based from base currency.
    $currency_default = round(1 / $rate_target_currency, 6);

    $recalculated = [];
    $recalculated[$base_currency] = $currency_default;

    // Recalculate all data.
    foreach ($rates as $currency => $rate) {
      if ($currency !== $target_currency) {
        $recalculated[$currency] = round($rate * $currency_default, 6);
      }
    }

    return new ExchangerProviderRates([
      'base' => $target_currency,
      'rates' => $recalculated,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    $exchange_rates = $this->buildExchangeRates();

    // Write new data.
    if (!empty($exchange_rates)) {
      $file = $this->configFactory->getEditable($this->getConfigName());
      $file->setData(['rates' => $exchange_rates]);
      $file->save();
    }
  }

}
