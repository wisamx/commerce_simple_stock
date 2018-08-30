<?php

namespace Drupal\commerce_simple_stock\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\commerce_product\Entity\ProductVariation;

class StockInventoryControlForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stock_inventory_control_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#theme'] = array('commerce_simple_stock_inventory_control_form');

    $form['sku'] = [
      '#type' => 'textfield',
      '#autocomplete_route_name' => 'commerce_simple_stock.sku_autocomplete',
      '#placeholder' => t('Type SKU number...'),
      '#required' => FALSE,
      '#title' => $this->t('SKU'),
    ];

    $form['product'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'commerce_product_variation',
      '#placeholder' => $this->t('Choose Product'),
      '#required' => FALSE,
      '#title' => $this->t('Choose Product'),
    ];

    $form['fill'] = [
      '#type' => 'submit',
      '#value' => $this->t('Fill'),
    ];

    $form['values'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('SKU'),
        $this->t('Quantity'),
        $this->t('Operations'),
      ],
    ];

    ///If we have user submitted values, that means this is triggered by form rebuild because of SKU not found
    $user_submit = $form_state->getValue('values');
    if (isset($user_submit)) {
      $invalidSKUPos = $form_state->getStorage();

      foreach ($user_submit as $pos => $row) {
        $value_form = &$form['values'][$pos];

        $value_form = [
          '#parents' => ['values', $pos]
        ];

        $value_form['sku'] = [
          '#type' => 'textfield',
          '#default_value' => $row['sku'],
          '#required' => TRUE,
          '#attributes' => ['readonly' => 'readonly'],
          '#prefix' => '<div class="sku">',
          '#suffix' => '</div>',
        ];

        if (isset($invalidSKUPos[$pos]) && $invalidSKUPos[$pos]) {
          $value_form['sku']['#attributes']['class'][] = 'error';
        }

        $value_form['quantity'] = [
          '#type' => 'number',
          '#default_value' => $row['quantity'],
          '#required' => TRUE,
          '#prefix' => '<div class="quantity">',
          '#suffix' => '</div>',
        ];

        $value_form['remove'] = [
          '#markup' => '<div type="button" class="button delete-item-button">Remove</div>',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $user_submit = &$form_state->getValue('values');
    if (empty($user_submit)) {
      $form_state->setErrorByName('sku', $this->t('Please at least provide one entry'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $op = $form_state->getValue('op');
    $user_submit = &$form_state->getValue('values');
    $real_submit = $form_state->getUserInput()['values'];

    if ($des == '') {
      $des = $op;
    } else {
      $des = $op . ': ' . $des;
    }

    // Clear outdated user submit values, these are fixed by users
    foreach ($real_submit as $pos => $row) {
      if (!isset($row['sku'])) {
        unset($user_submit[$pos]);
      }
    }

    // validate SKU first
    $invalidSKUPos = [];
    foreach ($user_submit as $pos => $row) {
      if (!$this->validateSku($row['sku'])) {
        $invalidSKUPos[$pos] = TRUE;
        drupal_set_message($this->t('SKU: @sku doesn\'t exist.', ['@sku' => $row['sku']]), 'error');
      }
    }

    if (count($invalidSKUPos) > 0) {
      $form_state->setStorage($invalidSKUPos);
      $form_state->setRebuild();
    } else {
      // When all SKUs are valid, process the submission
      foreach ($user_submit as $pos => $row) {

        $quantity = abs($row['quantity']);

        $query = \Drupal::entityQuery('commerce_product_variation');
        $variationIDs = $query->condition('sku', $row['sku'])->execute();

        $productVariation = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load(current($variationIDs));
        if ($productVariation->hasField('field_stock')) {
          if ($productVariation->field_stock->value == NULL ) {
            $productVariation->field_stock->value = 0;
          }
          $productVariation->field_stock->value = $productVariation->field_stock->value + $quantity;
          $productVariation->save();

          drupal_set_message($this->t($productVariation->getTitle() . ': ' . $productVariation->field_stock->value));
        }
      }
      drupal_set_message($this->t('Operation Succeeded!'));
    }
  }

  /**
   * If a sku exists in database.
   *
   * @param $sku
   */
  protected function validateSku($sku) {
    $result = \Drupal::entityQuery('commerce_product_variation')
      ->condition('sku', $sku)
      ->condition('status', 1)
      ->execute();

    return $result ? TRUE : FALSE;
  }

}
