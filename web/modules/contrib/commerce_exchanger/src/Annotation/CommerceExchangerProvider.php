<?php

namespace Drupal\commerce_exchanger\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Commerce exchange rates provider item annotation object.
 *
 *  Plugin namespace: Plugin\Commerce\ExchangerProvider.
 *
 * @see \Drupal\commerce_exchanger\ExchangerProviderManager
 * @see plugin_api
 *
 * @Annotation
 */
class CommerceExchangerProvider extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * If provider supports test / live mode.
   *
   * @var bool
   */
  public $modes = FALSE;

  /**
   * Base currency upon exchange rates are based.
   *
   * @var string
   */
  public $base_currency;

  /**
   * Define if external provider need api key.
   *
   * @var bool
   */
  public $api_key = FALSE;

  /**
   * Define if external provider need authentication.
   *
   * @var bool
   */
  public $auth = FALSE;

  /**
   * Define if external provider supports fetching by any currency.
   *
   * @var bool
   */
  public $enterprise = FALSE;

  /**
   * Define if external provider supports historical rates.
   *
   * @var bool
   */
  public $historical_rates = FALSE;

  /**
   * Define if external provider refresh currencies only once a day.
   *
   * @var bool
   */
  public $refresh_once = FALSE;

  /**
   * Define if plugin is manual, without importing data from external provider.
   *
   * @var bool
   */
  public $manual = FALSE;

  /**
   * Define what type of request required for fetching exchange rates.
   *
   * @var bool
   */
  public $method = 'GET';

  /**
   * Define if the rates needs to be transformed - reverse calculated.
   *
   * @var bool
   */
  public $transform_rates = FALSE;

}
