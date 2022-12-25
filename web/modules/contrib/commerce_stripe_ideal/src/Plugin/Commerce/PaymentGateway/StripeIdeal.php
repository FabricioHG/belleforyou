<?php

namespace Drupal\commerce_stripe_ideal\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentStorage;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsVoidsInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_stripe_ideal\Event\CommerceStripeIdealEvent;
use Drupal\commerce_stripe_ideal\Event\CommerceStripeIdealEvents;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Uuid\Php;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Stripe\Balance;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException as SignatureVerificationExceptionAlias;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod as StripePayementMethod;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\StripeObject;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\Core\Url;
use UnexpectedValueException;
use Drupal\commerce_payment\Entity\PaymentMethod;

/**
 * Provides the Off-site Redirect payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "commerce_stripe_ideal",
 *   label = "iDEAL through Stripe",
 *   payment_type = "payment_default",
 *   display_label = "iDEAL",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_stripe_ideal\PluginForm\StripeIdeal\PaymentOffsiteForm",
 *   },
 * )
 */
class StripeIdeal extends OffsitePaymentGatewayBase implements SupportsRefundsInterface, SupportsVoidsInterface {

  /**
   * Payment Storage.
   *
   * @var PaymentStorage
   */
  protected $paymentStorage;

  /**
   * Logger.
   *
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * UUID core service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * Event dispatcher.
   *
   * @var EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, LoggerInterface $logger, UuidInterface $uuid, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);
    if ($this->configuration['secret_key']) {
      Stripe::setApiKey($this->configuration['secret_key']);
      $this->logger = $logger;
    }
    try {
      $this->paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');
    } catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->critical($e->getMessage());
    }
    $this->uuid = $uuid;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('logger.factory')->get('commerce_stripe_ideal'),
      $container->get('uuid'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'publishable_key' => '',
        'secret_key' => '',
        'signing_secret' => '',
        'logo' => 0,
        'display_title_override' => '',
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#default_value' => $this->configuration['secret_key'],
      '#required' => FALSE,
    ];

    $form['publishable_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publishable Key'),
      '#default_value' => $this->configuration['publishable_key'],
      '#required' => FALSE,
    ];

    $form['signing_secret'] = [
      '#type' => 'textfield',
      '#title' => t('Signing secret'),
      '#description' => t('Used for verifying signatures using Stripe library. Check https://stripe.com/docs/webhooks/signatures for more info'),
      '#default_value' => $this->configuration['signing_secret'],
      '#required' => FALSE,
    ];
    $form['logo'] = [
      '#type' => 'checkbox',
      '#title' => t('Show logo only'),
      '#default_value' => $this->configuration['logo'],
    ];

    $form['display_title_override'] = [
      '#type' => 'textfield',
      '#title' => t('Override display title'),
      '#description' => t('Used for overriding display title. You may use HTML here if you want'),
      '#default_value' => $this->configuration['display_title_override'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      // Validate the secret key.
      $expected_livemode = $values['mode'] == 'live';
      if (!empty($values['secret_key'])) {
        try {
          Stripe::setApiKey($values['secret_key']);
          // Make sure we use the right mode for the secret keys.
          if (Balance::retrieve()->offsetGet('livemode') != $expected_livemode) {
            $form_state->setError($form['secret_key'], $this->t('The provided secret key is not for the selected mode (@mode).', ['@mode' => $values['mode']]));
          }
        } catch (ApiErrorException $e) {
          $form_state->setError($form['secret_key'], $this->t('Invalid secret key.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['secret_key'] = $values['secret_key'];
      $this->configuration['publishable_key'] = $values['publishable_key'];
      $this->configuration['signing_secret'] = $values['signing_secret'];
      $this->configuration['logo'] = $values['logo'];
      $this->configuration['display_title_override'] = $values['display_title_override'];
    }
  }

  /**
   * Create Authorisation Request.
   *
   * @param PaymentInterface $payment
   *   Payment.
   * @param string $returnUrl
   *   Return url.
   *
   * @return string
   *   Redirect Url.
   *
   * @throws EntityStorageException
   */
  public function createRequest(PaymentInterface $payment, $returnUrl) {
    if ($payment->getState()->value != 'new') {
      throw new InvalidArgumentException('The provided payment is in an invalid state.');
    }
    $url = FALSE;
    $payment_amount = $payment->getAmount();

    // Stripe iDeal payments require euro's. We can't convert here because we'd
    // charge in a different currency than was presented to the user.
    if ($payment_amount->getCurrencyCode() !== "EUR") {
      throw new DeclineException("Stripe iDEAL requires payments to be in euros");
    }

    $order = $payment->getOrder();

    $intent_id = $order->getData('commerce_stripe_ideal_intent');

    try {

      $intent = $intent_id ? PaymentIntent::retrieve($intent_id) : FALSE;

      $allowed_statuses = [
        PaymentIntent::STATUS_REQUIRES_ACTION,
        PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
        PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
      ];

      if ($intent instanceof PaymentIntent && in_array($intent->status, $allowed_statuses) && in_array('ideal', $intent->payment_method_types)) {
        if ($intent->next_action instanceof StripeObject) {
          $url = $intent->next_action->offsetGet('redirect_to_url')->offsetGet('url');
        } else {
          $intent = $intent->confirm([
            'return_url' => $returnUrl,
          ]);

          if ($intent->next_action instanceof StripeObject) {
            $url = $intent->next_action->offsetGet('redirect_to_url')->offsetGet('url');
          }
        }
      } else {
        $payment_method = StripePayementMethod::create([
          'type' => 'ideal',
        ]);

        $intent = PaymentIntent::create([
          'amount' => $this->toMinorUnits($order->getTotalPrice()),
          'currency' => 'eur',
          'payment_method_types' => ['ideal'],
          'payment_method' => $payment_method,
          'description' => $this->t('Order: ') . $order->id(),
          'metadata' => [
            'order_id' => $order->id(),
            'store_id' => $order->getStoreId(),
            'store_name' => $order->getStore()->label(),
            'email' => $order->getEmail(),
          ],
        ]);

        $order->setData('commerce_stripe_ideal_intent', $intent->id)->save();
        $order->setData('commerce_stripe_ideal_secret', $intent->client_secret)->save();

        $intent = $intent->confirm([
          'return_url' => $returnUrl,
        ]);

        if ($intent->next_action instanceof StripeObject) {
          $url = $intent->next_action->offsetGet('redirect_to_url')->offsetGet('url');
        }

        // Update the local payment entity.
        $payment->setState('authorization');
        $payment->setRemoteId($intent->id);
        $payment->save();
      }
    } catch (ApiErrorException $e) {
      $this->logger->warning($e->getMessage());
    }

    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $payment_intent = $request->get('payment_intent');
    $payment_intent_client_secret = $request->get('payment_intent_client_secret');
    $payment = NULL;
    $parameters = [];

    $payment = $this->findPayment($payment_intent, $payment_intent_client_secret);

    // Payment with matching payment intent and client secret not found.
    if (is_null($payment)) {
      throw new InvalidRequestException("'Invalid payment specified.");
    }

    $order = $payment->getOrder();
    if ($order->isPaid()) {
      throw new InvalidRequestException('Order for this payment is already paid in full');
    }

    if ($payment->getState()->value !== 'completed') {
      /** @var StripeIdeal $gateway */
      try {
        $intent = PaymentIntent::retrieve($payment_intent);
      } catch (ApiErrorException $e) {
        throw new InvalidRequestException('Unable to payment intent');
      }

      switch ($intent->status) {
        case PaymentIntent::STATUS_SUCCEEDED:
          $this->intentSucceeded($intent);
          $payment = $this->paymentStorage
            ->load($payment->id());
          break;

        case PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD:
        case PaymentIntent::STATUS_CANCELED:
        case PaymentIntent::STATUS_REQUIRES_ACTION:
          // Void transaction.
          $payment = $this->intentPaymentFailed($intent);
          $this->messenger()->addStatus('Payment failed.');
          break;
      }
    }

    // Now we know any outstanding actions have been resolved.
    // If the payment has been completed in webhooks there's nothing to do.
    switch ($payment->getState()->value) {
      case 'completed':
        $parameters = [
          'payment_intent' => $request->get('payment_intent'),
          'payment_intent_client_secret' => $request->get('payment_intent_client_secret'),
        ];
        $route = 'commerce_checkout.form';
        break;

      default:
        $route = 'commerce_checkout.form';
        break;
    }

    $url = Url::fromRoute($route, [
      'commerce_order' => $order->id(),
      'step' => 'checkout',
    ], [
      'query' => $parameters,
      'absolute' => TRUE,
    ])->toString();
    return RedirectResponse::create($url);
  }

  /**
   * Finds the payment by remote_id and client_secret combination.
   *
   * @param string $remote_id
   *   Remote id.
   * @param string|bool $client_secret
   *   If not empty checks if client secret matches the one on payment intent.
   *
   * @return Payment|null
   *   Either the found payment entity or null if nothing could be found.
   */
  public function findPayment($remote_id, $client_secret = FALSE) {
    /** @var Payment[] $payments */
    $payments = $this->paymentStorage
      ->loadByProperties([
        'remote_id' => $remote_id,
      ]);
    if (empty($payments)) {
      return NULL;
    }
    $payment = reset($payments);
    if ($payment instanceof Payment && $client_secret) {
      $order = $payment->getOrder();
      if ($order instanceof Order && $client_secret != $order->getData('commerce_stripe_ideal_secret')) {
        // Payment doesn't match client secret.
        $payment = NULL;
      }
    }

    return $payment;
  }

  /**
   * Handles the decoupled checkout
   * This is an alternative solution to create an payment, but the redirection is handled before
   */

  /**
   * Handles the payment_intent.succeeded of a Stripe webhook.
   *
   * @param PaymentIntent $intent
   *   The data Stripe provides for this event.
   *
   * @return Payment|null
   *   Payment.
   *
   * @throws EntityStorageException
   */
  protected function intentSucceeded(PaymentIntent $intent) {
    $payment = $this->findPayment($intent->id, $intent->client_secret);
    if ($payment instanceof Payment) {

      $order = $payment->getOrder();
      $paymentMethod = $order->get('payment_method')->getValue();
      $paymentGateway = $order->get('payment_gateway')->first()->entity;
      $workflow = $payment->getState()->getWorkflow();
      $transition = $workflow->getTransition('capture');
      $payment->getState()->applyTransition($transition);
      $request_time = $this->time->getRequestTime();
      $payment->setAuthorizedTime($request_time);
      $payment->setCompletedTime($request_time);
      $paymentMethodId = null;

      if (empty($paymentMethod)) {
        /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
        $paymentMethod = PaymentMethod::create([
          'type' => 'stripe_ideal',
          'payment_gateway' => $paymentGateway->id(),
          'uid' => $order->getCustomerId(),
          'remote_id' => $intent->payment_method,
        ]);
        $paymentMethod->setReusable(FALSE);
        $paymentMethod->setBillingProfile($order->getBillingProfile());
        $paymentMethod->save();
        $order->set('payment_method', $paymentMethod);
        $order->save();
        $paymentMethodId = $paymentMethod->id();
      } else {
        $paymentMethodId = $paymentMethod[0]->target_id;
      }

      $payment->payment_method = $paymentMethodId;
      $payment->save();

      $event = new CommerceStripeIdealEvent($payment);
      $this->eventDispatcher->dispatch(CommerceStripeIdealEvents::COMMERCE_STRIPE_IDEAL_PAYMENT_SUCCEEDED, $event);
    }
    return $payment;
  }

  /**
   * Handles the payment_intent.payment_failed event of a Stripe webhook.
   *
   * @param PaymentIntent $intent
   *   The data Stripe provides for this event.
   *
   * @return Payment|null
   *   Payment.
   *
   * @throws EntityStorageException
   */
  protected function intentPaymentFailed(PaymentIntent $intent) {
    $payment = $this->findPayment($intent->id, $intent->client_secret);
    if ($payment instanceof Payment) {
      $workflow = $payment->getState()->getWorkflow();
      $transition = $workflow->getTransition('void');
      $payment->getState()->applyTransition($transition);
      $payment->save();
      $event = new CommerceStripeIdealEvent($payment);
      $this->eventDispatcher->dispatch(CommerceStripeIdealEvents::COMMERCE_STRIPE_IDEAL_PAYMENT_FAILED, $event);
    }
    return $payment;
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    $content = $request->getContent();
    $event = NULL;
    $badResponse = FALSE;
    $sig_header = $request->server->get('HTTP_STRIPE_SIGNATURE');
    $endpoint_secret = $this->configuration['signing_secret'];

    try {
      $event = Webhook::constructEvent(
        $content,
        $sig_header,
        $endpoint_secret
      );
      $paymentIntent = $event->data->object;
      // We process only iDEAL payment intents.
      if ($paymentIntent instanceof PaymentIntent && !in_array('ideal', $paymentIntent->payment_method_types)) {
        return NULL;
      }
    } catch (UnexpectedValueException $e) {
      // Invalid payload.
      $this->logger->warning($e->getMessage());
      return new Response("", Response::HTTP_BAD_REQUEST);
    } catch (SignatureVerificationExceptionAlias $e) {
      // Invalid signature.
      $this->logger->warning($e->getMessage());
      return new Response("", Response::HTTP_BAD_REQUEST);
    }
    // Route the webhooks we're interested in to some dedicated functions.
    try {
      switch ($event->type) {
        case 'payment_intent.succeeded':
          $payment = $this->intentSucceeded($paymentIntent);
          $badResponse = is_null($payment) ? TRUE : FALSE;
          break;

        case 'payment_intent.payment_failed':
        case 'payment_intent.canceled':
          // Void transaction.
          $payment = $this->intentPaymentFailed($paymentIntent);
          $badResponse = is_null($payment) ? TRUE : FALSE;
          break;
      }
    } catch (EntityStorageException $e) {
      $this->logger->warning($e->getMessage());
    }

    if ($badResponse) {
      return new Response('Could not find payment', Response::HTTP_BAD_REQUEST);
    }

    return NULL;
  }

  /**
   * Handles the decoupled checkout
   * This is an alternative solution to create an payment, but the redirection is handled before
   * within your local decoupled controller/service
   * @param OrderInterface $order
   * @param Request $request
   */
  public function onDecoupledReturn(OrderInterface $order, Request $request) {
    $payment_intent = $request->get('payment_intent');
    $payment_intent_client_secret = $request->get('payment_intent_client_secret');

    $payment = $this->findPayment($payment_intent, $payment_intent_client_secret);


    /** @var StripeIdeal $gateway */
    try {
      $paymentIntent = PaymentIntent::retrieve($payment_intent);
    } catch (ApiErrorException $e) {
      throw new InvalidRequestException('Unable to payment intent');
    }

    switch ($paymentIntent->status) {
      case PaymentIntent::STATUS_SUCCEEDED:
        /*
            if payment is missing create a new payment. State is set to completed,
                since the payment is processed on the frontend side
            */
        if (!$payment) {
          $remoteId = $payment_intent;
          $current_user = $order->getCustomerId();
          $paymentGateway = $order->get('payment_gateway')->entity;
          $remoteId = $request->get('payment_intent');
          if ($paymentIntent->status != PaymentIntent::STATUS_SUCCEEDED) {
            $remoteId = '';
          }

          if (empty($remoteId) && $this->request->has('payment_intent')) {
            Drupal::logger('commerce_stripe_ideal')
              ->error(t('Payment failed at the payment server @order_id. @message', [
                '@order_id' => $order->id()
              ]));
          }

          $currentTime = $this->time->getRequestTime();

          $paymentMethod = $order->get('payment_method')->getValue();
          if (empty($paymentMethod)) {
            /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
            $paymentMethod = PaymentMethod::create([
              'type' => 'stripe_ideal',
              'payment_gateway' => $paymentGateway->id(),
              'uid' => $order->getCustomerId(),
              'remote_id' => $paymentIntent->payment_method,
            ]);
            $paymentMethod->setReusable(FALSE);
            $paymentMethod->setBillingProfile($order->getBillingProfile());
            $paymentMethod->save();
            $order->set('payment_method', $paymentMethod);
            $order->save();
          }

          $payment = Payment::create([
            'state' => 'completed',
            'amount' => $order->getTotalPrice(),
            'payment_gateway' => $paymentGateway->id(),
            'payment_method' => $paymentMethod->id(),
            'order_id' => $order->id(),
            'remote_id' => $remoteId,
            'remote_state' => $paymentIntent->status,
            'payment_gateway_mode' => $paymentGateway->getPlugin()->getMode(),
            'expires' => 0,
            'uid' => $current_user,
            'completed' => $currentTime,
            'authorized' => $currentTime,
          ]);

          $payment_data['completed'] = $this->time->getCurrentTime();
          $payment_data['authorized'] = $this->time->getCurrentTime();

          $payment->save();

          $order->setData('commerce_stripe_ideal_secret', $request->get('payment_intent_client_secret'))
            ->save();
        }
        break;

      case PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD:
      case PaymentIntent::STATUS_CANCELED:
      case PaymentIntent::STATUS_REQUIRES_ACTION:
        // Void transaction.
        $payment = $this->intentPaymentFailed($paymentIntent);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    try {
      $intent = PaymentIntent::retrieve($payment->getRemoteId());
      $minor_units_amount = $this->toMinorUnits($amount);
      $data = [
        'charge' => reset($intent->charges->data),
        'amount' => $minor_units_amount,
      ];
      // Refund and support for Idempotent Requests.
      // https://stripe.com/docs/api/idempotent_requests
      Refund::create($data, ['idempotency_key' => $this->uuid->generate()]);
    } catch (ApiErrorException $e) {
      $this->logger->warning($e->getMessage());
    }

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->setState('partially_refunded');
    } else {
      $payment->setState('refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);
    // Void Stripe payment - release uncaptured payment.
    try {
      $intent = PaymentIntent::retrieve($payment->getRemoteId());
      if ($intent instanceof PaymentIntent) {
        $statuses_to_void = [
          PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
          PaymentIntent::STATUS_REQUIRES_ACTION,
          PaymentIntent::STATUS_REQUIRES_CAPTURE,
          PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
        ];
        if (!in_array($intent->status, $statuses_to_void)) {
          throw new PaymentGatewayException('The PaymentIntent cannot be voided because its not in allowed status.');
        }
        $intent->cancel();
      } else {
        $message = $this->t('Payment intent could not be retrieved from Stripe. Please check data on Stripe.');
        $this->logger->warning($message);
        throw new PaymentGatewayException($message);
      }
    } catch (ApiErrorException $e) {
      $this->logger->warning($e->getMessage());
      throw new PaymentGatewayException('Void failure. Please check Stripe Logs for more info.');
    }

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel() {
    if ($this->configuration['display_title_override']) {
      return Markup::create($this->configuration['display_title_override'])->__toString();
    }
    if ($this->configuration['logo']) {
      return Markup::create('<img alt="ideal" src="https://www.ideal.nl/img/statisch/iDEAL-klein.gif">')->__toString();
    }

    return parent::getDisplayLabel();
  }
}
