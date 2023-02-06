<?php

namespace Drupal\commerce_exchanger;

class DefaultExchangerCalculator extends AbstractExchangerCalculator {

  /**
   * {@inheritdoc}
   */
  public function getExchangerId() {
    // Return the first one.
    foreach ($this->providers as $provider) {
      if ($provider->status()) {
        return $provider->getExchangerConfigName();
      }
    }

    return NULL;
  }

}
