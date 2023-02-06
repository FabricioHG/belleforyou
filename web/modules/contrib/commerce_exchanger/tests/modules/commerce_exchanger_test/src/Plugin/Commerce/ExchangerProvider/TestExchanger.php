<?php

namespace Drupal\commerce_exchanger_test\Plugin\Commerce\ExchangerProvider;

use Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider\ExchangerProviderRemoteBase;

/**
 * Provides the test exchange rates.
 *
 * @CommerceExchangerProvider(
 *   id = "test",
 *   label = "Test",
 *   display_label = "Test",
 *   historical_rates = TRUE,
 *   base_currency = "HRK",
 *   refresh_once = TRUE,
 *   transform_rates = TRUE,
 * )
 */
class TestExchanger extends ExchangerProviderRemoteBase {

  /**
   * {@inheritdoc}
   */
  public function apiUrl() {
    return 'http://example.test';
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteData($base_currency = NULL) {
    return [
      'EUR' => 0.13,
      'USD' => 0.16,
      'AUD' => 0.22,
    ];
  }

}
