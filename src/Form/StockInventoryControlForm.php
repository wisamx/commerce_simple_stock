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
      '#placeholder' => t('Scan or Type SKU number...'),
      '#required' => FALSE,
      '#title' => $this->t('SKU'),
    ];

    //$locations = \Drupal::entityTypeManager()->getStorage('commerce_simple_stock_location')->loadMultiple();

    /*$options = [];
    $user = \Drupal::currentUser();
    foreach ($locations as $lid => $location) {
      if ($user->hasPermission('administer stock entity') || $user->hasPermission('edit stock entity at any location')) {
        $options[$lid] = $location->get('name')->value;
      } else if ($user->hasPermission('edit stock entity at own location')) {
        $uids = $location->get('uid');
        foreach ($uids as $manager) {
          if ($manager->target_id == $user->id()) {
            $options[$lid] = $location->get('name')->value;
          }
        }
      }
    }*/

    $form['actions'] = array(
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    );

    $form['actions']['fill'] = [
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

    /* //If we have user submitted values, that means this is triggered by form rebuild because of SKU not found
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
        $value_form['title'] = [
          '#type' => 'textfield',
          '#default_value' => $row['title'],
          '#required' => TRUE,
          '#attributes' => ['readonly' => 'readonly'],
          '#prefix' => '<div class="title">',
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
	*/
	
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
      $des = $op . ': ' .$des;
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
          $productVariation->field_stock->value = $productVariation->field_stock->value + $quantity;
          $productVariation->save();
		  
      }
      drupal_set_message($this->t('Operation: ' . $op . ' succeeded!'));
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

  /**
   *
   * @return \Drupal\commerce_simple_stock\Entity\StockInterface
   */
  /*protected function getStock($sku, $location_id) {
    $connection = Database::getConnection('default', NULL);
    $query = $connection->select('commerce_product_variation__stock', 'cs');
    $query->join('commerce_product_variation_field_data', 'cr', 'cr.variation_id=cs.entity_id');
    $query->join('commerce_simple_stock_field_data', 'csf', 'csf.stock_id=cs.stock_target_id');
    $query->fields('cs', ['stock_target_id']);
    $query->condition('cr.sku', $sku);
    $query->condition('csf.stock_location', $location_id);

    $stock_id = $query->execute()->fetchField();

    if ($stock_id) {
      return \Drupal::entityTypeManager()->getStorage('commerce_simple_stock')->load($stock_id);
    } else return NULL;
  }*/

}
