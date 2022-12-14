<?php

namespace Drupal\customfeeds\Plugin\migrate_plus\data_parser\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The configuration form for the json migrate data parser plugin.
 *
 * @MigrateForm(
 *   id = "xml_form",
 *   title = @Translation("Xml Data Parser Plugin Form"),
 *   form_type = "configuration",
 *   parent_id = "xml",
 *   parent_type = "data_parser"
 * )
 */
class XmlForm extends DataParserFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $source = $this->migration->getSourceConfiguration();

    $form['item_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('XML Item Selector'),
      '#description' => $this->t('Enter XML root with one leading forward slash, for example "@example".', [
        '@example' => '/root/row',
      ]),
      '#default_value' => $source['item_selector'] ?: '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $entity->source['item_selector'] = $form_state->getValue('item_selector');
  }

}
