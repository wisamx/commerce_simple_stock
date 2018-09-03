<?php

namespace Drupal\commerce_simple_stock\OrderProcessor;

use Drupal\commerce\AvailabilityManagerInterface;
use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class AvailabilityOrderProcessor implements OrderProcessorInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  public function process(OrderInterface $order) {
    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity) {
        $order_quantity = floatval($order_item->getQuantity());

        if ($purchased_entity->hasField('field_stock') && $purchased_entity->field_stock->value != NULL) {

          $stock = (integer) $purchased_entity->field_stock->value;
          $allow_negative = \Drupal::config('commerce_simple_stock.settings')->get('allow_backorder');

          // Remove order item there is no quantity available.
          if (empty($stock) || $stock <= 0) {
            if (!$allow_negative) {
              $order->removeItem($order_item);
              $order_item->delete();
              $this->messenger()->addError(
                $this->t('@purchasable is no longer available and has been removed from your order.', [
                  '@purchasable' => $purchased_entity->label()
                ])
              );
            }
            else {
              $this->messenger()->addError(
                $this->t('@purchasable may be backordered.', [
                  '@purchasable' => $purchased_entity->label()
                ])
              );
            }
          }
          // Adjust to max quantity available if over.
          elseif ($stock < $order_quantity) {
            if (!$allow_negative) {
              $this->messenger()->addError(
                $this->t('@purchasable only has @quantity available. Your order has been updated.', [
                  '@purchasable' => $purchased_entity->label(),
                  '@quantity' => $stock,
                ])
              );
              $order_item->setQuantity($stock);
            }
            else {
              $this->messenger()->addError(
                $this->t('@purchasable may be partially backordered.', [
                  '@purchasable' => $purchased_entity->label()
                ])
              );
            }
          }
        }
      }
    }
  }
}