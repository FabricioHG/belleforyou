<?php

namespace Drupal\commerce_stripe_ideal\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Provides the ideal payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_ideal",
 *   label = @Translation("Stripe iDeal"),
 *   create_label = @Translation("Stripe iDeal"),
 * )
 */
class StripeIdeal extends PaymentMethodTypeBase {

    /**
     * {@inheritdoc}
     */
    public function buildLabel(PaymentMethodInterface $payment_method) {
        return $this->pluginDefinition['label'];
    }
}
