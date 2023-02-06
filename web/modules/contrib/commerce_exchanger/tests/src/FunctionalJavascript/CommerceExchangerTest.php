<?php

namespace Drupal\Tests\commerce_exchanger\FunctionalJavascript;

use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_exchanger\Entity\ExchangeRatesInterface;
use Drupal\commerce_exchanger\Exception\ExchangeRatesDataMismatchException;
use Drupal\commerce_price\Entity\Currency;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;

/**
 * Tests the commerce exchanger UI.
 *
 * @group commerce_exchanger
 */
class CommerceExchangerTest extends CommerceWebDriverTestBase {

  /**
   * Price in HRK currency.
   *
   * @var \Drupal\commerce_price\Price
   */
  protected $priceHrk;

  /**
   * Price in USD currency.
   *
   * @var \Drupal\commerce_price\Price
   */
  protected $priceUsd;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_price',
    'commerce_exchanger',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce exchanger settings',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Add additional currency.
    // The parent has already imported USD.
    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('HRK');

    $this->priceHrk = new Price('100', 'HRK');
    $this->priceUsd = new Price('100', 'USD');
  }

  /**
   * Tests adding a exchange rate.
   */
  public function testCommerceExchangerCreation() {
    $this->drupalGet('admin/commerce/config/exchange-rates');
    $this->getSession()->getPage()->clickLink('Add Exchange rates');

    $this->getSession()->getPage()->fillField('label', 'European Central Bank');
    $this->getSession()->getPage()->selectFieldOption('plugin', 'ecb');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->pressButton('Edit');
    $this->getSession()->getPage()->fillField('id', 'ecb');

    $add = [
      'label' => 'European Central Bank',
      'id' => 'ecb_test',
      'plugin' => 'ecb',
    ];
    $this->submitForm($add, 'Save');

    $this->assertSession()->pageTextContains(t('Saved the @label exchange rates.', ['@label' => 'European Central Bank']));

    /** @var \Drupal\commerce_exchanger\Entity\ExchangeRatesInterface $exchange_rates */
    $exchange_rates = ExchangeRates::load('ecb_test');

    $this->assertEquals('ecb', $exchange_rates->getPluginId());
    $this->assertEquals('European Central Bank', $exchange_rates->label());
    $this->assertEquals(ExchangeRatesInterface::COMMERCE_EXCHANGER_IMPORT . 'ecb_test', $exchange_rates->getExchangerConfigName());

    $rates = $this->config($exchange_rates->getExchangerConfigName())->get('rates');

    $this->assertIsArray($rates);
    $this->assertIsArray($rates['USD']['HRK']);
    $this->assertEquals('0', $rates['USD']['HRK']['value']);

    $this->drupalGet('admin/commerce/config/exchange-rates');
    $this->getSession()->getPage()->clickLink('Run import');

    $rates = $this->config($exchange_rates->getExchangerConfigName())->get('rates');
    $this->assertIsArray($rates);
    $this->assertIsArray($rates['USD']['HRK']);
    $this->assertIsNotString($rates['USD']['HRK']['value']);
    $this->assertIsFloat($rates['USD']['HRK']['value']);

  }

  /**
   * Tests adding a exchange rate without enough currencies.
   */
  public function testCommerceExchangerCreationDisabled() {
    $hrk = Currency::load('HRK');
    $hrk->delete();
    $this->drupalGet('admin/commerce/config/exchange-rates');
    $this->getSession()->getPage()->clickLink('Add Exchange rates');
    $this->assertSession()->pageTextContains(t('Minimum of two currencies needs to be enabled, to be able to add exchange rates'));
  }

  /**
   * Tests editing a exchange rate.
   */
  public function testCommerceExchangerEditing() {
    $exchange_rates = $this->createEntity('commerce_exchange_rates', [
      'label' => 'ECB',
      'id' => 'ecb',
      'plugin' => 'ecb',
      'status' => TRUE,
    ]);

    // There is no rates upon creation.
    $this->expectException(ExchangeRatesDataMismatchException::class);
    $this->container->get('commerce_exchanger.calculate')->priceConversion($this->priceHrk, 'USD');

    // Import rates.
    $this->drupalGet('admin/commerce/config/exchange-rates');
    $this->getSession()->getPage()->clickLink('Run import');

    $price_test = $this->container->get('commerce_exchanger.calculate')->priceConversion($this->priceHrk, 'USD');
    $this->assertNotEquals($price_test->getNumber(), '100.00');

    $this->drupalGet('admin/commerce/config/exchange-rates/' . $exchange_rates->id() . '/edit');

    $edit = [
      'label' => 'ECB edited',
      'plugin' => 'fixer',
      'configuration[ecb][enterprise]' => 1,
      'status' => 0,
    ];

    $this->submitForm($edit, 'Save');

    $exchange_rates = ExchangeRates::load('ecb');
    $this->assertEquals($edit['label'], $exchange_rates->label());
    $this->assertNotEquals($edit['plugin'], $exchange_rates->getPluginId());
    $this->assertEquals($edit['status'], $exchange_rates->status());
    $this->assertNotEquals($edit['configuration[ecb][enterprise]'], $exchange_rates->getPluginConfiguration()['enterprise']);
  }

  /**
   * Tests deleting a exchange rate.
   */
  public function testCommerceExchangerDeletion() {
    $exchange_rates = $this->createEntity('commerce_exchange_rates', [
      'label' => 'ECB',
      'id' => 'ecb',
      'plugin' => 'ecb',
    ]);

    $config_rates = $exchange_rates->getExchangerConfigName();
    $entity_id = $exchange_rates->id();

    $this->drupalGet('admin/commerce/config/exchange-rates/' . $entity_id . '/delete');
    $this->assertSession()->pageTextContains(t('Are you sure you want to delete the exchange rates @exchange_rate?', ['@exchange_rate' => $exchange_rates->label()]));
    $this->assertSession()->pageTextContains(t('This action cannot be undone.'));
    $this->submitForm([], 'Delete');

    $exchange_rates_exists = (bool) ExchangeRates::load($entity_id);
    $this->assertEmpty($exchange_rates_exists, 'The exchange rates has been deleted from the database.');

    $exchange_rates_config = $this->config($config_rates)->getRawData();
    $this->assertEmpty($exchange_rates_config, 'The exchange rates configuration file has been deleted.');

    $this->assertSession()->pageTextContains(t('There are no exchange rates entities yet.'));

  }

}
