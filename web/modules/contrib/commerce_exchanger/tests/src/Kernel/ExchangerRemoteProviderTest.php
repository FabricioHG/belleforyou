<?php

namespace Drupal\Tests\commerce_exchanger\Kernel;

use Drupal\commerce_exchanger\ExchangerProviderRates;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * @coversDefaultClass \Drupal\commerce_exchanger\ExchangerProviderRates
 * @group commerce_exchanger
 */
class ExchangerRemoteProviderTest extends CommerceKernelTestBase {

  /**
   * @covers ::__construct
   */
  public function testImport() {
    $this->expectException(\InvalidArgumentException::class);
    $definition = [
      'base' => 'HRK',
      'rates' => [
        'EUR' => 'abs',
      ],
      'transform' => FALSE,
    ];
    new ExchangerProviderRates($definition);
  }

  /**
   * @covers ::__construct
   * @covers ::getBaseCurrency
   * @covers ::getRates
   * @covers ::isTransform
   * @covers ::getCurrencies
   */
  public function testValid() {
    // Can't use a unit test because DrupalDateTime objects use \Drupal.
    $definition = [
      'base' => 'HRK',
      'rates' => [
        'EUR' => '7.55',
        'USD' => '6.45',
      ],
    ];
    $rates = new ExchangerProviderRates($definition);

    $this->assertEquals($definition['base'], $rates->getBaseCurrency());
    $this->assertEquals($definition['rates'], $rates->getRates());
    $this->assertFalse($rates->isTransform());
    $this->assertCount(2, $rates->getRates());
    $this->assertEmpty($rates->getCurrencies());
  }

  /**
   * @covers ::__construct
   * @covers ::getBaseCurrency
   * @covers ::getRates
   * @covers ::isTransform
   * @covers ::getCurrencies
   */
  public function testValidFiltered() {
    // Can't use a unit test because DrupalDateTime objects use \Drupal.
    $definition = [
      'base' => 'HRK',
      'rates' => [
        'EUR' => '7.55',
        'USD' => '6.45',
        'UAH' => '5.40',
      ],
      'currencies' => [
        'EUR' => 'Euro',
        'USD' => 'Dolar',
        'HRK' => 'Croatian Kuna',
      ],
      'transform' => TRUE,
    ];
    $rates = new ExchangerProviderRates($definition);

    $this->assertEquals($definition['base'], $rates->getBaseCurrency());
    $this->assertNotEquals($definition['rates'], $rates->getRates());
    $this->assertTrue($rates->isTransform());
    $this->assertCount(3, $rates->getCurrencies());
    $this->assertNotEquals($definition['rates']['EUR'], $rates->getRates()['EUR']);
    $this->assertEquals(round(1 / 7.55, 6), $rates->getRates()['EUR']);
    $this->assertEquals(2, count($rates->getRates()));
  }

}
