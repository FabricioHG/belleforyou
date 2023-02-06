<?php

namespace Drupal\commerce_exchanger\Controller;

use Drupal\commerce_exchanger\ExchangerImporterInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class ExchangerImport.
 *
 * @package Drupal\commerce_exchanger\Controller
 */
class ExchangerImport extends ControllerBase {

  /**
   * Exchange importer.
   *
   * @var \Drupal\commerce_exchanger\ExchangerImporterInterface
   */
  protected $importer;

  /**
   * ExchangerImport constructor.
   *
   * @param \Drupal\commerce_exchanger\ExchangerImporterInterface $importer
   *   Exchange importer.
   */
  public function __construct(ExchangerImporterInterface $importer) {
    $this->importer = $importer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_exchanger.import')
    );
  }

  /**
   * Force import of all exchange rates.
   */
  public function run() {
    $this->importer->run(TRUE);
    $url = Url::fromRoute('entity.commerce_exchange_rates.collection')->toString();
    return new RedirectResponse($url);
  }

}
