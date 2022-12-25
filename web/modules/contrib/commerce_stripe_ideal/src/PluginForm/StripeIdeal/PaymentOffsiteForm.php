<?php

namespace Drupal\commerce_stripe_ideal\PluginForm\StripeIdeal;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class PaymentOffsiteForm.
 *
 * @package Drupal\commerce_stripe_ideal\PluginForm\StripeIdeal
 */
class PaymentOffsiteForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    /** @var \Drupal\commerce_stripe_ideal\Plugin\Commerce\PaymentGateway\StripeIdeal $gateway */
    $gateway = $payment->getPaymentGateway()->getPlugin();

    $redirect_url = $gateway->createRequest($payment, $form['#return_url']);

    return $this->buildRedirectForm($form, $form_state, $redirect_url, [], BasePaymentOffsiteForm::REDIRECT_GET);
  }

}
