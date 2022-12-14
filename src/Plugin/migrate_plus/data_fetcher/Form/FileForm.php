<?php

namespace Drupal\feeds_migrate\Plugin\migrate_plus\data_fetcher\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The configuration form for the file migrate data fetcher plugin.
 *
 * @MigrateForm(
 *   id = "file_form",
 *   title = @Translation("File Data Fetcher Plugin Form"),
 *   form_type = "configuration",
 *   parent_id = "file",
 *   parent_type = "data_fetcher"
 * )
 */
class FileForm extends DataFetcherFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $source = $this->migration->getSourceConfiguration();

    $form['directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File Upload Directory'),
      // @todo move this to defaultConfiguration
      '#default_value' => $source['data_fetcher_directory'] ?: 'public://migrate',
    ];

    $fids = [];
    if (!empty($source['urls'])) {
      foreach ($source['urls'] as $file_uri) {
        if (!empty($file_uri)) {
          /** @var \Drupal\file\FileInterface[] $file */
          $files = \Drupal::entityTypeManager()
            ->getStorage('file')
            ->loadByProperties(['uri' => $file_uri]);
          if (!empty($files)) {
            /** @var \Drupal\file\FileInterface $file */
            $file = reset($files);
            $fids[] = $file->id();
          }
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!empty($fids)) {
      $fids = $form_state->getValue('urls', []);
      /** @var \Drupal\file\FileInterface[] $file */
      $files = $this->entityTypeManager
        ->getStorage('file')
        ->loadMultiple($fids);

      // Save the uploaded files.
      foreach ($files as $file) {
        $file->setPermanent();
        $file->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Handle file upload directory.
    $entity->source['data_fetcher_directory'] = $form_state->getValue('directory');

    // Handle file uploads.
    unset($entity->source['urls']);
    $fids = $form_state->getValue('urls');
    if (!empty($fids)) {
      /** @var \Drupal\file\FileInterface[] $file */
      $files = $this->entityTypeManager
        ->getStorage('file')
        ->loadMultiple($fids);
      foreach ($files as $file) {
        $file_uri = $file->getFileUri();
        $entity->source['urls'][] = $file_uri;
      }
    }
  }

}
