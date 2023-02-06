<?php

namespace Drupal\check_cart_currency\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Check_cart_currency routes.
 */
class CheckCartCurrencyController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
