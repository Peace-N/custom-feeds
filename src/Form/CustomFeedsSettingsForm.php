<?php

namespace Drupal\customfeeds\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\customfeeds\CustomFeedsInterface;

/**
 * Class CustomFeedsSettingsForm
 * @package Drupal\customfeeds\Form
 */
class CustomFeedsSettingsForm extends FormBase
{
  /**
   * Returns a Unique Form Identifier
   * @return string
   */
  public function getFormId(): string
  {
    return CustomFeedsInterface::ADMIN_SETTINGS_FORM_ID;
  }

  /**
   * Build Form for Submitting Values
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form = [];
    $collection = \Drupal::state()->get(CustomFeedsInterface::CUSTOM_FEEDS_CONFIG_VALUES);//todo//getter state
    $form[CustomFeedsInterface::ADMIN_SETTINGS_RSS_ATOM_FEED_NAME] = [
      '#type' => 'textfield',
      '#title' => $this->t('RSS FEED NAME'),
      '#description' => $this->t('This is the name you would like to give this Feed'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $collection[CustomFeedsInterface::ADMIN_SETTINGS_RSS_ATOM_FEED_NAME],
    ];

    $form[CustomFeedsInterface::ADMIN_SETTINGS_RSS_ATOM_FEED_URL] = [
      '#type' => 'textfield',
      '#title' => $this->t('RSS FEED URL'),
      '#description' => $this->t('This is the RSS FEED URL'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $collection[CustomFeedsInterface::ADMIN_SETTINGS_RSS_ATOM_FEED_URL],
    ];

    $form[CustomFeedsInterface::ADMIN_SETTINGS_RSS_ATOM_FEED_SCHEDULE] = [
      '#type' => 'select',
      '#title' => $this->t('IMPORT SCHEDULE'),
      '#description' => $this->t('This is the Import Schedule Used by the Scheduler (CRON)'),
      '#options' => [
        '-1' => $this->t('Off'),
        '900' => $this->t('Every 15 Minutes'),
        '1800' => $this->t('Every 30 Minutes'),
        '3600' => $this->t('Every 60 Minutes'),
        '86400' => $this->t('Every 1 Day'),
      ],
      '#required' => TRUE,
      '#default_value' => $collection[CustomFeedsInterface::ADMIN_SETTINGS_RSS_ATOM_FEED_SCHEDULE],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Feed Setting'),
      '#button_type' => $this->t('primary'),
    ];

    return $form;
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $collection = $form_state->cleanValues()->getValues();
    \Drupal::state()->set(CustomFeedsInterface::CUSTOM_FEEDS_CONFIG_VALUES, $collection);
    $messenger = \Drupal::service('messenger');
    $messenger->addMessage($this->t('You Custom Feed settings and configuration has been saved'));
  }
}
