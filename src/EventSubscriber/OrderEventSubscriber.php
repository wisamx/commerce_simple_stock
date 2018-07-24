<?php

namespace Drupal\commerce_simple_stock\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listen to Commerce Order events.
 */
class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['commerce_order.place.post_transition'] = 'postTransitionOrder';
    return $events;
  }

  public function postTransitionOrder(WorkflowTransitionEvent $event, $event_name) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
	
    foreach ($order->getItems() as $order_item) {
	  if ($order_item->hasPurchasedEntity()) {
		  $purchasedEntity = $order_item->getPurchasedEntity();
		  if ($purchasedEntity->hasField('field_stock')) {
			  $purchasedEntity->field_stock->value = $purchasedEntity->field_stock->value - $order_item->getQuantity();
			  if ($purchasedEntity->field_stock->value < 0)
				  $purchasedEntity->field_stock->value = 0;
			  $purchasedEntity->save();
		  }
	  }
    }
	
  }
}