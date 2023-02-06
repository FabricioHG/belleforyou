<?php

namespace Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider;

use Drupal\Component\Serialization\Json;

/**
 * Provides the Open Exchange Rates API integration.
 *
 * @CommerceExchangerProvider(
 *   id = "open_exchange_rates",
 *   label = "Open Exchange Rates",
 *   display_label = "Open Exchange Rates",
 *   historical_rates = TRUE,
 *   enterprise = TRUE,
 *   api_key= TRUE,
 * )
 */
class OpenExchangeRatesExchanger extends ExchangerProviderRemoteBase {

  /**
   * {@inheritdoc}
   */
  public function apiUrl() {
    return 'https://openexchangerates.org/api/latest.json';
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteData($base_currency = NULL) {
    $data = NULL;

    $options = [
      'query' => ['app_id' => $this->getApiKey()],
    ];

    // Add base currency if we use enterprise model.
    if (!empty($base_currency) && $this->isEnterprise()) {
      $options['query']['base'] = $base_currency;
    }

    $request = $this->apiClient($options);

    if ($request) {
      $json = Json::decode($request);

      if (!empty($json['rates'])) {
        unset($json['timestamp'], $json['license'], $json['disclaimer']);

        // This structure is what we need.
        $data = $json;
      }
    }

    return $data;
  }

}
