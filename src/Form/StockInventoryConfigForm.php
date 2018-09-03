<?php

namespace Drupal\commerce_simple_stock\Form;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Commerce Simple Stock settings.
 */
class StockInventoryConfigForm extends ConfigFormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_simple_stock_settings';
  }

   /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_simple_stock.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_simple_stock.settings');

    $form['allow_backorder'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow backorder'),
      '#default_value' => $config->get('allow_backorder'),
      '#description' => $this->t('Allows negative stock values, and doesn\'t prevent line items for more than stock on-hand.'),
    ];


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
    $this->configFactory->getEditable('commerce_simple_stock.settings')
      ->set('allow_backorder', $form_state->getValue('allow_backorder'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
