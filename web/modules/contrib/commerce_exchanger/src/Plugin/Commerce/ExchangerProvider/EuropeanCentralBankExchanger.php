<?php

namespace Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider;

use \SimpleXMLElement;

/**
 * Provides EuropeanCentralBank.
 *
 * @CommerceExchangerProvider(
 *   id = "ecb",
 *   label = "European Central Bank",
 *   display_label = "European Central Bank",
 *   historical_rates = TRUE,
 *   base_currency = "EUR",
 *   refresh_once = TRUE,
 * )
 */
class EuropeanCentralBankExchanger extends ExchangerProviderRemoteBase {

  /**
   * {@inheritdoc}
   */
  public function apiUrl() {
    return 'http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteData($base_currency = NULL) {
    $data = NULL;

    $request = $this->apiClient([]);

    if ($request) {

      try {
        $xml = new SimpleXMLElement($request);
      }
      catch (\Exception $e) {
        $this->logger->error($e->getMessage());
      }

      $data = [];

      // Loop and build array.
      foreach ($xml->Cube->Cube->Cube as $rate) {
        $code = (string) $rate['currency'];
        $rate = (string) $rate['rate'];
        $data[$code] = $rate;
      }

    }

    return $data;
  }

}
