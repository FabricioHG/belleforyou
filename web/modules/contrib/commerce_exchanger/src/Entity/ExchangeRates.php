<?php

namespace Drupal\commerce_exchanger\Entity;

use Drupal\commerce\CommerceSinglePluginCollection;
use Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider\ExchangerProviderRemoteInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Exchange rates entity.
 *
 * @ConfigEntityType(
 *   id = "commerce_exchange_rates",
 *   label = @Translation("Exchange rates"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\commerce_exchanger\ExchangeRatesListBuilder",
 *     "form" = {
 *       "add" = "Drupal\commerce_exchanger\Form\ExchangeRatesForm",
 *       "edit" = "Drupal\commerce_exchanger\Form\ExchangeRatesForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer commerce exchanger settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "plugin",
 *     "configuration",
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/exchange-rates/add",
 *     "edit-form" = "/admin/commerce/config/exchange-rates/{commerce_exchange_rates}/edit",
 *     "delete-form" = "/admin/commerce/config/exchange-rates/{commerce_exchange_rates}/delete",
 *     "collection" = "/admin/commerce/config/exchange-rates"
 *   }
 * )
 */
class ExchangeRates extends ConfigEntityBase implements ExchangeRatesInterface {

  /**
   * The Exchange rates ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Exchange rates label.
   *
   * @var string
   */
  protected $label;

  /**
   * The exchange rates weight.
   *
   * @var int
   */
  protected $weight;

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * The plugin collection that holds the exchange provider plugin.
   *
   * @var \Drupal\commerce\CommerceSinglePluginCollection
   */
  protected $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function getExchangerConfigName() {
    if (!$this->id) {
      return NULL;
    }
    return self::COMMERCE_EXCHANGER_IMPORT . $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginId($plugin_id) {
    $this->plugin = $plugin_id;
    $this->configuration = [];
    $this->pluginCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginConfiguration(array $configuration) {
    $this->configuration = $configuration;
    $this->pluginCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'configuration' => $this->getPluginCollection(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) {
    // Invoke the setters to clear related properties.
    if ($property_name == 'plugin') {
      $this->setPluginId($value);
    }
    elseif ($property_name == 'configuration') {
      $this->setPluginConfiguration($value);
    }
    else {
      return parent::set($property_name, $value);
    }
  }

  /**
   * Gets the plugin collection that holds the exchange provider plugin.
   *
   * Ensures the plugin collection is initialized before returning it.
   *
   * @return \Drupal\commerce\CommerceSinglePluginCollection
   *   The plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $plugin_manager = \Drupal::service('plugin.manager.commerce_exchanger_provider');
      $this->pluginCollection = new CommerceSinglePluginCollection($plugin_manager, $this->plugin, $this->configuration, $this->id);
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Declare dependency on config where we store exchange rates.
    // So when we delete entity that those data are also deleted.
    $this->addDependency('config', $this->getExchangerConfigName());

    /** @var \Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider\ExchangerProviderInterface $plugin */
    $plugin = $this->getPlugin();

    if (empty($this->getPluginConfiguration()) && $plugin instanceof ExchangerProviderRemoteInterface) {
      $definition = $plugin->getPluginDefinition();
      $configuration = $plugin->defaultConfiguration();

      if (!empty($definition['base_currency'])) {
        $configuration['use_cross_sync'] = !empty($definition['base_currency']);
        $configuration['base_currency'] = $definition['base_currency'];
      }

      $configuration['refresh_once'] = $definition['refresh_once'] ?? FALSE;
      $configuration['transform_rates'] = $definition['transform_rates'] ?? FALSE;

      $this->setPluginConfiguration($configuration);
    }

  }

}
