<?php

namespace Drupal\commerce_exchanger;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Exchange rates entities.
 */
class ExchangeRatesListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Exchange rates');
    $header['id'] = $this->t('Machine name');
    $header['mode'] = $this->t('Mode');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $configuration = $entity->getPluginConfiguration();
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['mode'] = $configuration['mode'] ?? 'live';
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    // You probably want a few more properties here...
    return $row + parent::buildRow($entity);
  }

}
