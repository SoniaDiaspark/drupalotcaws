<?php
/**
 * @file
 * Contains Drupal\otc_group_email\Form\OtcMailForm.
 */
namespace Drupal\otc_group_email\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
/**
 * Class SettingsForm.
 *
 * 
 */
class OtcMailForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */

  public function defaultConfiguration() {
    $default_config = \Drupal::config('otc_group_email.settings');
    return array(      
      'otc_group_email' => $default_config->get('otc_group_email'),     
    );
  }

  /**
   * {@inheritdoc}
   */
  
  protected function getEditableConfigNames() {
    return [
      'otc_group_email.settings',
    ];
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'otc_group_email_settings_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('otc_group_email.settings');
    	
	$form['otc_group_email'] = array(
      '#title' => $this->t('Group Email Address'),
      '#type' => 'textfield',
      '#size' => 30,
      '#default_value' => $config->get('otc_group_email'),
    );
    
    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('otc_group_email.settings')      
	  ->set('otc_group_email', $form_state->getValue('otc_group_email'))	  
      ->save();
  }
}