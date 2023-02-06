<?php

namespace Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider;

/**
 * Defines an interface for remote sources.
 */
interface ExchangerProviderRemoteInterface {

  /**
   * URL from remote provider upon API call should be made.
   *
   * @return string
   *   Returns full url.
   */
  public function apiUrl();

  /**
   * Generic wrapper around Drupal http client.
   *
   * @param array $options
   *   Additional request options.
   *
   * @return mixed
   *   Return response, or error.
   */
  public function apiClient(array $options);

  /**
   * Method which supports remote provider.
   *
   * @return string
   *   Either GET or POST.
   */
  public function getMethod();

  /**
   * Determine if remote provider supports querying by different base currency.
   *
   * @return bool
   *   Return true or false.
   */
  public function isEnterprise();

  /**
   * Remote providers api key.
   *
   * @return string
   *   Return user entered api key or empty string.
   */
  public function getApiKey();

  /**
   * Remote authentication credentials.
   *
   * @return array
   *   Returns multiple parameters needed for authentication.
   */
  public function getAuthData();

  /**
   * Determine if rates provided by provider needs to be transformed
   * in a required rate ratio based on base currency.
   *
   * @return bool
   *   Return true if transfrom rates is ON.
   */
  public function transformRates();

  /**
   * Either remote provider defined base currency, or use entered.
   *
   * @return string
   *   Return currency three letter code - ISO 4217.
   */
  public function getBaseCurrency();

  /**
   * Either remote provider defined or use defined choice.
   *
   * @return bool
   *   Return true if all currencies values are derived from base.
   */
  public function useCrossSync();

  /**
   * Fetch external data.
   *
   * @param string|null $base_currency
   *   If we fetch data based on specific currency.
   */
  public function getRemoteData($base_currency = NULL);

}
