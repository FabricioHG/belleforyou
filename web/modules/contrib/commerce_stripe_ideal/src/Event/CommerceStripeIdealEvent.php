<?php

namespace Drupal\commerce_stripe_ideal\Event;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the event for Commerce Stripe Ideal.
 *
 * @see \Drupal\commerce_stripe_ideal\Event\CommerceStripeIdealEvents
 */
class CommerceStripeIdealEvent extends Event {

  /**
   * The order.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentInterface
   */
  protected $payment;

  /**
   * Constructs a new FilterPaymentGatewaysEvent object.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   */
  public function __construct(PaymentInterface $payment) {
    $this->payment = $payment;
  }

  /**
   * Return payment.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   *   Payment.
   */
  public function getPayment() {
    return $this->payment;
  }

}
