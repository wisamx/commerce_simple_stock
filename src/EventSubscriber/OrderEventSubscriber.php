<?php

namespace Drupal\commerce_simple_stock\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderItemEvent;
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
    $this->entity_type_manager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];

    $events['commerce_order.place.post_transition'] = ['postTransitionOrder', -100];
    $events['commerce_order.cancel.post_transition'] = ['postTransitionCancelOrder', -100];
    $events[OrderEvents::ORDER_UPDATE] = ['onOrderUpdate', -100];
    $events[OrderEvents::ORDER_PREDELETE] = ['onOrderDelete', -100];
    $events[OrderEvents::ORDER_ITEM_PREDELETE] = 'preDeleteOrderItem';
    $events[OrderEvents::ORDER_ITEM_PRESAVE] = 'preSaveOrderItem';

    return $events;
  }

  public function postTransitionOrder(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();
    $allow_negative = \Drupal::config('commerce_simple_stock.settings')->get('allow_backorder');

    foreach ($order->getItems() as $order_item) {

      if ($order_item->hasPurchasedEntity()) {
        $purchasedEntity = $order_item->getPurchasedEntity();
        if ($purchasedEntity->hasField('field_stock') && $purchasedEntity->field_stock->value != NULL) {
          $purchasedEntity->field_stock->value = $purchasedEntity->field_stock->value - $order_item->getQuantity();
          if ($purchasedEntity->field_stock->value < 0 && !$allow_negative) {
            $purchasedEntity->set('field_stock', 0);
          }
          $purchasedEntity->save();
        }
      }

    }

  }

  public function postTransitionCancelOrder(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();
    if ($order->original && $order->original->getState()->value === 'draft') {
      return;
    }

    foreach ($order->getItems() as $order_item) {
      if ($order_item->hasPurchasedEntity()) {
        $purchasedEntity = $order_item->getPurchasedEntity();
        if ($purchasedEntity->hasField('field_stock') && $purchasedEntity->field_stock->value != NULL) {
          $purchasedEntity->field_stock->value = $purchasedEntity->field_stock->value + $order_item->getQuantity();
          $purchasedEntity->save();
        }
      }
    }
  }

  public function preDeleteOrderItem(OrderItemEvent $event) {
    $item = $event->getOrderItem();
    $order = $item->getOrder();

    if ($order && !in_array($order->getState()->value, ['draft', 'canceled'])) {

      $quantity = $item->getQuantity();

      if ($quantity) {
        $purchasedEntity = $item->getPurchasedEntity();
        if (!$purchasedEntity) {
          return;
        }
        if ($purchasedEntity->hasField('field_stock') && $purchasedEntity->field_stock->value != NULL) {
          $purchasedEntity->field_stock->value = $purchasedEntity->field_stock->value + $quantity;
          $purchasedEntity->save();
        }
      }
    }
  }

  public function preSaveOrderItem(OrderItemEvent $event) {
    $item = $event->getOrderItem();
    $order = $item->getOrder();

    if ($order && !in_array($order->getState()->value, ['draft', 'canceled'])) {

      $diff = $item->original->getQuantity() - $item->getQuantity();

      if ($diff) {
        $purchasedEntity = $item->getPurchasedEntity();
        if (!$purchasedEntity) {
          return;
        }
        if ($purchasedEntity->hasField('field_stock') && $purchasedEntity->field_stock->value != NULL) {
          $purchasedEntity->field_stock->value = $purchasedEntity->field_stock->value + $diff;
          $purchasedEntity->save();
        }
      }
    }
  }

  public function onOrderDelete(OrderEvent $event) {
    $order = $event->getOrder();
    if (in_array($order->getState()->value, ['draft', 'canceled'])) {
      return;
    }
    $items = $order->getItems();
    foreach ($items as $item) {
      $purchasedEntity = $item->getPurchasedEntity();
      if (!$purchasedEntity) {
        continue;
      }
      if ($purchasedEntity->hasField('field_stock') && $purchasedEntity->field_stock->value != NULL) {
        $purchasedEntity->field_stock->value = $purchasedEntity->field_stock->value + $item->getQuantity();
        $purchasedEntity->save();
      }
    }
  }

  public function onOrderUpdate(OrderEvent $event) {
    $order = $event->getOrder();
    $original_order = $order->original;
    $allow_negative = \Drupal::config('commerce_simple_stock.settings')->get('allow_backorder');

    foreach ($order->getItems() as $item) {
      if (!$original_order->hasItem($item)) {
        if ($order && !in_array($order->getState()->value, ['draft', 'canceled'])) {
          $purchasedEntity = $item->getPurchasedEntity();

          if (!$purchasedEntity) {
            continue;
          }

          if ($purchasedEntity->hasField('field_stock') && $purchasedEntity->field_stock->value != NULL) {
            $purchasedEntity->field_stock->value = $purchasedEntity->field_stock->value - $item->getQuantity();
            if ($purchasedEntity->field_stock->value < 0 && !$allow_negative) {
              $purchasedEntity->field_stock->value = 0;
            }
            $purchasedEntity->save();
          }

        }
      }
    }

  }
}
