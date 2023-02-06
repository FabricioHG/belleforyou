<?php

namespace Drupal\commerce_exchanger;

use Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider\ExchangerProviderInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\commerce_exchanger\Annotation\CommerceExchangerProvider;

/**
 * Provides the Commerce exchange rates provider plugin manager.
 */
class ExchangerProviderManager extends DefaultPluginManager {

  /**
   * Constructs a new ExchangerProviderManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Commerce/ExchangerProvider', $namespaces, $module_handler, ExchangerProviderInterface::class, CommerceExchangerProvider::class);

    $this->alterInfo('commerce_exchanger_provider_info');
    $this->setCacheBackend($cache_backend, 'commerce_exchanger_provider_plugins');
  }

}
