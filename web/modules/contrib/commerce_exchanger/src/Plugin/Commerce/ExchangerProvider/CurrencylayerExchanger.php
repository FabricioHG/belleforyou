<?php

namespace Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider;

use Drupal\Component\Serialization\Json;

/**
 * Provides the Currencylayer.com exchange rates.
 *
 * @CommerceExchangerProvider(
 *   id = "currencylayer",
 *   label = "Currencylayer",
 *   display_label = "currencylayer.com",
 *   historical_rates = TRUE,
 *   enterprise = TRUE,
 *   api_key= TRUE,
 * )
 */
class CurrencylayerExchanger extends ExchangerProviderRemoteBase {

  /**
   * {@inheritdoc}
   */
  public function apiUrl() {
    if ($this->isEnterprise()) {
      return 'https://api.currencylayer.com/live';
    }
    return 'http://api.currencylayer.com/live';
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
    if ($this->isEnterprise()) {
      $options['query']['base'] = $base_currency;
    }

    $request = $this->apiClient($options);

    if ($request) {
      $json = Json::decode($request);

      if (!empty($json['success'])) {

        // Leave base currency. In some cases we don't know base currency.
        // Currenclayer on free plan uses your address for base currency, and in
        // Drupal you could have different default value.
        $data['base'] = $json['source'];

        foreach ($json['quotes'] as $code => $rate) {
          $data['rates'][str_replace($json['source'], '', $code)] = $rate;
        }
      }
    }

    return $data;
  }

}
