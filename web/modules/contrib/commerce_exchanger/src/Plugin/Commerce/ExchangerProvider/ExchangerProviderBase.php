<?php

namespace Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider;

use Drupal\commerce_exchanger\Entity\ExchangeRatesInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Commerce exchanger provider plugins.
 */
abstract class ExchangerProviderBase extends PluginBase implements ExchangerProviderInterface, ContainerFactoryPluginInterface {

  /**
   * The currency storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $currencyStorage;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * Configuration management.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Return formatted array of currencies ['HRK' => 'Croatian Kuna'].
   *
   * @var array
   */
  protected $currencies;

  /**
   * Parent entity if present.
   *
   * @var mixed
   */
  private $entityId;

  /**
   * Constructs a new ExchangeProvider object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Currency storage.
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   Drupal http client.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   * @param \Psr\Log\LoggerInterface $logger_channel
   *   Logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ClientFactory $http_client_factory, ConfigFactory $config_factory, LoggerInterface $logger_channel) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currencyStorage = $entity_type_manager->getStorage('commerce_currency');
    $this->httpClientFactory = $http_client_factory;
    $this->configFactory = $config_factory;
    $this->logger = $logger_channel;

    if (array_key_exists('_entity_id', $configuration)) {
      $this->entityId = $configuration['_entity_id'];
      unset($configuration['_entity_id']);
    }
    $this->setConfiguration($configuration);

    $this->currencies = $this->getCurrencies();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('http_client_factory'),
      $container->get('config.factory'),
      $container->get('logger.factory')->get('commerce_exchanger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'cron' => 1,
      'api_key' => '',
      'auth' => [],
      'use_cross_sync' => FALSE,
      'enterprise' => FALSE,
      'demo_amount' => 100,
      'base_currency' => '',
      'refresh_once' => FALSE,
      'manual' => FALSE,
      'mode' => 'live',
      'transform_rates' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function supportingModes() {
    return $this->pluginDefinition['modes'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMode() {
    return $this->configuration['mode'] ?? 'live';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $definition = $this->getPluginDefinition();

    // Skip everything for manual plugin.
    if (empty($definition['manual'])) {

      $form['api_key'] = [
        '#type' => 'textfield',
        '#title' => t('Api Key'),
        '#description' => t('Api key'),
        '#default_value' => $this->configuration['api_key'] ?? NULL,
        '#required' => $definition['api_key'] ?? FALSE,
        '#access' => $definition['api_key'] ?? FALSE,
      ];

      $auth = $definition['auth'] ?? FALSE;

      $form['auth'] = [
        '#type' => 'details',
        '#title' => t('Authentication'),
        '#open' => TRUE,
        '#tree' => TRUE,
        '#access' => $auth,
      ];

      $form['auth']['username'] = [
        '#type' => 'textfield',
        '#title' => t('Username'),
        '#description' => t('Api key'),
        '#default_value' => $this->configuration['auth']['username'] ?? NULL,
        '#required' => $auth,
        '#access' => $auth,
      ];

      $form['auth']['password'] = [
        '#type' => 'textfield',
        '#title' => t('Password'),
        '#description' => t('Api key'),
        '#default_value' => $this->configuration['auth']['password'] ?? NULL,
        '#required' => $auth,
        '#access' => $auth,
      ];

      $enterprise = empty($definition['base_currency']) ? $this->configuration['enterprise'] : FALSE;

      $form['enterprise'] = [
        '#type' => 'checkbox',
        '#default_value' => $enterprise,
        '#title' => t('Enterprise'),
        '#description' => t('If provider supports querying per multiple base currencies, not just single base currency'),
        '#disabled' => !empty($definition['base_currency']),
      ];

      $cross_sync = !empty($definition['base_currency']) ? TRUE : $this->configuration['use_cross_sync'] ?? FALSE;

      $form['use_cross_sync'] = [
        '#type' => 'checkbox',
        '#default_value' => $cross_sync,
        '#title' => t('Use cross conversion between non default currencies.'),
        '#description' => t('If enabled only the rates between the default currency and the other currencies have to be managed. The rates between the other currencies is derived from their rates relative to the default currency.'),
        '#disabled' => !empty($definition['base_currency']),
      ];

      $currencies = $this->currencies;

      $form['base_currency'] = [
        '#type' => 'select',
        '#title' => t('Base currency'),
        '#description' => t('Select base currency upon all others are calculated, if your providers does not support Enterprise mode'),
        '#options' => $currencies,
        '#default_value' => $definition['base_currency'] ?? $this->configuration['base_currency'],
        '#required' => !empty($this->configuration['enterprise']),
        '#access' => empty($this->configuration['enterprise']),
        '#disabled' => !empty($definition['base_currency']),
      ];

      $form['cron'] = [
        '#type' => 'select',
        '#title' => t('Exchange rates cron'),
        '#description' => t('Select how often exchange rates should be imported. Note about EBC, they update exchange rates once a day'),
        '#options' => [
          1 => t('Once a day'),
          2 => t('12 hours'),
          3 => t('8 hours'),
          4 => t('6 hours'),
          6 => t('4 hours'),
          8 => t('3 hours'),
          12 => t('2 hours'),
          24 => t('1 hour')
        ],
        '#default_value' => $definition['refresh_once'] ?? $this->configuration['cron'],
        '#disabled' => $definition['refresh_once'],
      ];
    }

    // Check if supporting different modes (test, live).
    if ($this->supportingModes()) {
      $form['mode'] = [
        '#type' => 'radios',
        '#title' => t('Mode'),
        '#options' => ['live' => t('Live'), 'test' => t('Test')],
        '#default_value' => $this->configuration['mode'],
        '#required' => TRUE,
      ];
    }

    else {
      $form['mode'] = [
        '#type' => 'value',
        '#value' => 'live',
      ];
    }

    $form['demo_amount'] = [
      '#type' => 'textfield',
      '#title' => t('Amount for example conversion:'),
      '#size' => 10,
      '#default_value' => $this->configuration['demo_amount'] ?? 100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $values ?? []);
    }
  }

  /**
   * Simple key-value array for enabled currencies.
   *
   * @return array
   *   Return formatted array of currencies ['HRK' => 'Croatian Kuna'].
   */
  protected function getCurrencies() {
    $currency_storage = $this->currencyStorage->loadMultiple();

    $currencies = [];

    foreach ($currency_storage as $currency) {
      $currencies[$currency->getCurrencyCode()] = $currency->label();
    }

    return $currencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigName() {
    return ExchangeRatesInterface::COMMERCE_EXCHANGER_IMPORT . $this->entityId;
  }

}
