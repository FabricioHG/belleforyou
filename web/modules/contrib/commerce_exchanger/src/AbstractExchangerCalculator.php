<?php

namespace Drupal\commerce_exchanger;

use Drupal\commerce_exchanger\Exception\ExchangeRatesDataMismatchException;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class AbstractExchangerCalculator.
 *
 * @package Drupal\commerce_exchanger
 */
abstract class AbstractExchangerCalculator implements ExchangerCalculatorInterface {

  /**
   * Entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * List of providers.
   *
   * @var \Drupal\commerce_exchanger\Entity\ExchangeRatesInterface[]
   */
  protected $providers;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Drupal commerce price rounder service.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * DefaultExchangerCalculator constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Drupal config factory.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   Drupal commerce price rounder service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactory $config_factory, RounderInterface $rounder) {
    $this->configFactory = $config_factory;
    $this->providers = $entity_type_manager->getStorage('commerce_exchange_rates')->loadMultiple();
    $this->rounder = $rounder;
  }

  /**
   * {@inheritdoc}
   */
  public function priceConversion(Price $price, string $target_currency) {
    $exchange_rates_config = $this->getExchangerId();

    if (empty($exchange_rates_config)) {
      throw new ExchangeRatesDataMismatchException('Not any active Exchange rates present');
    }

    // Price currency.
    $price_currency = $price->getCurrencyCode();

    // If someone is trying to convert same currency.
    if ($price_currency == $target_currency) {
      return $price;
    }

    $exchange_rates = $this->getExchangeRates();

    // Determine rate.
    $rate = $exchange_rates[$price_currency][$target_currency]['value'] ?? 0;

    // Don't allow multiply with zero or one.
    if (empty($rate)) {
      throw new ExchangeRatesDataMismatchException('There are no exchange rates set for ' . $price_currency . ' and ' . $target_currency);
    }

    // Convert amount to target currency.
    $price = $price->convert($target_currency, (string) $rate);
    $price = $this->rounder->round($price);
    return $price;
  }

  /**
   * {@inheritdoc}
   */
  public function getExchangeRates() {
    return $this->configFactory->get($this->getExchangerId())->get('rates') ?? [];
  }

}
