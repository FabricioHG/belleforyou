<?php

namespace Drupal\commerce_exchanger_test\Plugin\Commerce\ExchangerProvider;

use Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider\ExchangerProviderRemoteBase;

/**
 * Provides the enterprise exchange rates.
 *
 * @CommerceExchangerProvider(
 *   id = "enterprise",
 *   label = "Enterprise",
 *   display_label = "Enterprise",
 *   historical_rates = TRUE,
 *   enterprise = TRUE,
 *   api_key= TRUE,
 * )
 */
class TestEnterpriseExchanger extends ExchangerProviderRemoteBase {

  /**
   * {@inheritdoc}
   */
  public function apiUrl() {
    return 'http://example.enterprise';
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteData($base_currency = NULL) {
    $data = [
      'HRK' => [
        'EUR' => 0.13,
        'USD' => 0.16,
        'AUD' => 0.22,
      ],
      'EUR' => [
        'AUD' => 1.66,
        'HRK' => 7.58,
        'USD' => 1.19,
      ],
      'USD' => [
        'AUD' => 1.40,
        'HRK' => 6.39,
        'EUR' => 0.84,
      ],
      'AUD' => [
        'EUR' => 0.60,
        'HRK' => 4.56,
        'USD' => 0.71,
      ],
    ];

    return $data[$base_currency];
  }

}
