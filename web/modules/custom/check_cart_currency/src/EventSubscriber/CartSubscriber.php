<?php

/**
 * @file
 * Contains \Drupal\check_cart_currency\EventSubscriber\CartSubscriber.
 */
    
namespace Drupal\check_cart_currency\EventSubscriber;
    
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
    
/**
 * Code to run in conjunction with cart.
 */
class CartSubscriber implements EventSubscriberInterface {
    
  /**
	 * The messenger.
	 *
	 * @var \Drupal\Core\Messenger\MessengerInterface 
	 */
  protected $messenger;
  /**
	 * Constructs event subscriber.
	 *
	 * @param \Drupal\Core\Messenger\MessengerInterface $messenger 
	 * The messenger.
	 */
  

  public function __construct(MessengerInterface $messenger) { 
  	$this->messenger = $messenger;
  }

  public static function getSubscribedEvents() {
    $events[CartEvents::CART_ENTITY_ADD] = 'onCartAdd';
   	
   	return $events;
  }

  public function onCartAdd(CartEntityAddEvent $cartEvent) {
  	$carrito_existente = $cartEvent->getCart();
  	$divisa_producto_agregar = $cartEvent->getOrderItem();
  	$language = \Drupal::languageManager()->getCurrentLanguage()->getId();

  	$divisa_en_carrito = $carrito_existente->getTotalPrice()->getCurrencyCode();
  	$divisa_producto_agregar = $divisa_producto_agregar->getTotalPrice()->getCurrencyCode();
  
  	//$propiedades = get_object_vars($cart);
  	if ($divisa_en_carrito != $divisa_producto_agregar) {
  		//Enviar mensaje despues de boorar el item con diferente divsa
  	 	$this->messenger->addWarning(t("El producto que intentas agregar tiene una divisa diferente a la que se encuentra en tu carrito, te invitamos a que busques el mismo producto con la divisa que ya se encuentra en tu orden."));
  	 	if ($language == 'es') {
  	 		(new RedirectResponse('/cart'))->send();
    		exit();
  	 	}else{
  	 		(new RedirectResponse('/'.$language.'/cart'))->send();
    		exit();
  	 	} 	
  	}  	  
  }
    
}