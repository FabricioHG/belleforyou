<?php

namespace Drupal\Tests\commerce_exchanger\Kernel;

use Drupal\commerce_exchanger\Entity\ExchangeRates;
use Drupal\commerce_exchanger\Exception\ExchangeRatesDataMismatchException;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the exchanger calculator.
 *
 * @coversDefaultClass \Drupal\commerce_exchanger\DefaultExchangerCalculator
 * @group commerce_exchanger
 */
class CommerceExchangerCalculatorTest extends CommerceKernelTestBase {

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
   * ExchangerRates entity.
   *
   * @var \Drupal\commerce_exchanger\Entity\ExchangeRatesInterface
   */
  protected $exchanger;

  /**
   * Configuration file name.
   *
   * @var string
   */
  protected $exchangerId;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'commerce_exchanger',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // The parent has already imported USD.
    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('HRK');

    $this->priceHrk = new Price('100', 'HRK');
    $this->priceUsd = new Price('100', 'USD');

    $exchanger = ExchangeRates::create([
      'label' => 'ECB Rate',
      'id' => 'ecb_rates',
      'plugin' => 'ecb',
      'status' => TRUE,
    ]);
    $exchanger->save();

    $this->exchanger = $exchanger;
    $this->exchangerId = $exchanger->getExchangerConfigName();

    $this->config($this->exchangerId)->setData([
      'rates' => [
        'HRK' => [
          'USD' => [
            'value' => 0.15,
            'sync' => 0,
          ],
        ],
        'USD' => [
          'HRK' => [
            'value' => 6.85,
            'sync' => 0,
          ],
        ],
      ],
    ])->save();
  }

  /**
   * @covers ::getExchangerId
   */
  public function testExchangerId() {
    $this->assertEquals($this->exchangerId, $this->container->get('commerce_exchanger.calculate')->getExchangerId());
  }

  /**
   * @covers ::getExchangerId
   */
  public function testExchangerIdEmpty() {
    $this->exchanger->setStatus(FALSE);
    $this->exchanger->save();
    $this->assertEmpty($this->container->get('commerce_exchanger.calculate')->getExchangerId());

    $this->assertEmpty($this->container->get('commerce_exchanger.calculate')->getExchangeRates());

    $this->expectException(ExchangeRatesDataMismatchException::class);
    $this->container->get('commerce_exchanger.calculate')->priceConversion($this->priceUsd, 'HRK');

    $this->expectException(ExchangeRatesDataMismatchException::class);
    $this->container->get('commerce_exchanger.calculate')->priceConversion($this->priceHrk, 'USD');
  }

  /**
   * @covers ::getExchangeRates
   */
  public function testExchangeRates() {
    $this->assertArrayHasKey('HRK', $this->container->get('commerce_exchanger.calculate')->getExchangeRates());
    $this->assertArrayHasKey('USD', $this->container->get('commerce_exchanger.calculate')->getExchangeRates());
  }

  /**
   * @covers ::getExchangeRates
   */
  public function testExchangeRatesEmpty() {
    $this->exchanger->setStatus(FALSE);
    $this->exchanger->save();
    $this->assertEmpty($this->container->get('commerce_exchanger.calculate')->getExchangeRates());
  }

  /**
   * @covers ::priceConversion
   */
  public function testPriceConversion() {
    $priceHrk = $this->container->get('commerce_exchanger.calculate')->priceConversion($this->priceHrk, 'USD');
    $this->assertEquals(100 * 0.15, $priceHrk->getNumber());

    $priceUsd = $this->container->get('commerce_exchanger.calculate')->priceConversion($this->priceUsd, 'HRK');
    $this->assertEquals(100 * 6.85, $priceUsd->getNumber());

    $price_equal = $this->container->get('commerce_exchanger.calculate')->priceConversion($this->priceUsd, 'USD');
    $this->assertEquals(100.00, $price_equal->getNumber());

    $this->config($this->exchangerId)->setData([
      'rates' => [
        'HRK' => [
          'USD' => [
            'value' => 0,
            'sync' => 0,
          ],
        ],
        'USD' => [
          'HRK' => [
            'value' => '0',
            'sync' => 0,
          ],
        ],
      ],
    ])->save();

    $this->expectException(ExchangeRatesDataMismatchException::class);
    $this->container->get('commerce_exchanger.calculate')->priceConversion($this->priceUsd, 'HRK');

    $this->expectException(ExchangeRatesDataMismatchException::class);
    $this->container->get('commerce_exchanger.calculate')->priceConversion($this->priceHrk, 'USD');

  }

  /**
   * @covers ::priceConversion
   */
  public function testPriceConversionEmpty() {
    $this->exchanger->setStatus(FALSE);
    $this->exchanger->save();
    $this->expectException(ExchangeRatesDataMismatchException::class);
    $this->container->get('commerce_exchanger.calculate')->priceConversion($this->priceHrk, 'USD');
  }

}
