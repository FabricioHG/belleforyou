<?php

namespace Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider;

use Drupal\Component\Serialization\Json;

/**
 * Provides the Fixer.io exchange rates.
 *
 * @CommerceExchangerProvider(
 *   id = "fixer",
 *   label = "Fixer.io",
 *   display_label = "Fixer.io",
 *   historical_rates = TRUE,
 *   enterprise = TRUE,
 *   api_key= TRUE,
 * )
 */
class FixerExchanger extends ExchangerProviderRemoteBase {

  /**
   * {@inheritdoc}
   */
  public function apiUrl() {
    return 'http://data.fixer.io/api/latest';
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteData($base_currency = NULL) {
    $data = NULL;

    $options = [
      'query' => ['access_key' => $this->getApiKey()],
    ];

    // Add base currency if we use enterprise model.
    if (!empty($base_currency) && $this->isEnterprise()) {
      $options['query']['base'] = $base_currency;
    }

    $request = $this->apiClient($options);

    if ($request) {
      $json = Json::decode($request);

      if (!empty($json['success'])) {
        // Leave base currency. In some cases we don't know base currency.
        // Fixer.io on free plan uses your address for base currency, and in
        // Drupal you could have different default value.
        unset($json['timestamp'], $json['success'], $json['date']);

        // This structure is what we need.
        $data = $json;
      }
    }

    return $data;
  }

}
