<?php

namespace Drupal\check_cart_currency\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "check_cart_currency_example",
 *   admin_label = @Translation("Example"),
 *   category = @Translation("Check_cart_currency")
 * )
 */
class ExampleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
