<?php

namespace Drupal\commerce_stripe_ideal\Event;

/**
 * Defines events for the Commerce Stripe iDEAL module.
 */
final class CommerceStripeIdealEvents {
  /**
   * Name of the event fired when payment succeeded.
   *
   * @Event
   *
   * @see \Drupal\commerce_stripe_ideal\Event\CommerceStripeIdealEvent
   */
  const COMMERCE_STRIPE_IDEAL_PAYMENT_SUCCEEDED = 'commerce_stripe_ideal.payment_succeeded';

  /**
   * Name of the event fired when payment failed.
   *
   * @Event
   *
   * @see \Drupal\commerce_stripe_ideal\Event\CommerceStripeIdealEvent
   */
  const COMMERCE_STRIPE_IDEAL_PAYMENT_FAILED = 'commerce_stripe_ideal.payment_failed';

}
