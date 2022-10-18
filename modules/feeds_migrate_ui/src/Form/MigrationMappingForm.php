<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\feeds_migrate\MappingFieldFormInterface;
use Drupal\feeds_migrate\MappingFieldFormManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for listing/saving mapping settings.
 *
 * @package Drupal\feeds_migrate\Form
 */
class MigrationMappingForm extends EntityForm {

  /**
   * Plugin manager for migration mapping plugins.
   *
   * @var \Drupal\feeds_migrate\MappingFieldFormManager
   */
  protected $mappingFieldManager;

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Creates a new MigrationMappingForm.
   *
   * @param \Drupal\feeds_migrate\MappingFieldFormManager $mapping_field_manager
   *   Mapping field manager service.
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   *   Field manager service.
   */
  public function __construct(MappingFieldFormManager $mapping_field_manager, EntityFieldManager $field_manager) {
    $this->mappingFieldManager = $mapping_field_manager;
    $this->fieldManager = $field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.feeds_migrate.mapping_field_form'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Build mapping table.
    $form['mappings'] = [
      '#type' => 'table',
      '#header' => $this->getTableHeader(),
      '#empty' => $this->t('Please add mappings to this migration.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'row-weight',
        ],
      ],
    ];

    // Load migration process configuration.
    $weight = 0;
    foreach ($this->entity->getMappings() as $target => $mapping) {
      foreach ($mapping as $property_name => $property_mapping) {
        $form['mappings'][$target . '/' . $property_name] = $this->buildTableRow($form, $form_state, $property_mapping, $weight);
        $weight++;
      }
    }

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * Gets the mapping table header.
   *
   * @return array
   *   The headers.
   */
  protected function getTableHeader() {
    $header = [];

    $header['destination'] = [
      'data' => $this->t('Destination'),
    ];

    $header['source'] = [
      'data' => $this->t('Source'),
    ];

    $header['summary'] = [
      'data' => $this->t('Summary'),
    ];

    $header['unique'] = [
      'data' => $this->t('Unique'),
    ];

    $header['weight'] = [
      'data' => $this->t('Weight'),
    ];

    $header['operations'] = [
      'data' => $this->t('Operations'),
      'colspan' => 2,
    ];

    return $header;
  }

  /**
   * Builds the table row.
   *
   * @param array $form
   *   The complete mapping form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   * @param array $mapping
   *   The mapping configuration.
   * @param int $weight
   *   The index number of the mapping.
   *
   * @return array
   *   The built field row.
   */
  protected function buildTableRow(array $form, FormStateInterface $form_state, array $mapping, $weight) {
    $operations = [];
    $property = $mapping['destination']['property'] ?? NULL;
    $config = [
      'destination_key' => $mapping['destination']['key'],
      'mapping' => $mapping,
    ];
    $plugin = $this->getMappingFieldFormPlugin($config['destination_key'], $config);

    // Initialize our row.
    $row = [
      '#attributes' => [
        'class' => ['draggable'],
      ],
      'destination' => [],
      'source' => [],
      'summary' => [],
      'unique' => [],
      'weight' => [],
      'operations' => [],
    ];

    // Whenever applicable, use the field label as our destination value.
    $row['destination'] = [
      '#type' => 'label',
      '#title' => $destination = $plugin->getLabel($property),
    ];

    // Add the weight column.
    $row['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#title_display' => 'invisible',
      '#default_value' => $weight,
      '#delta' => 30,
      '#attributes' => [
        'class' => ['row-weight'],
      ],
    ];

    // Source.
    $row['source'] = [
      '#markup' => is_array($mapping['source']) ? implode('<br />', $mapping['source']) : $mapping['source'],
    ];

    // Summary of process plugins.
    $row['summary'] = $this->buildSummary($plugin, $property);

    // Unique.
    $row['unique'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Unique'),
      '#title_display' => 'invisible',
      '#default_value' => $mapping['unique'] ?? NULL,
      '#disabled' => TRUE,
    ];

    // Operations.
    $operations['edit'] = [
      'title' => $this->t('Edit'),
      'url' => new Url(
        'entity.migration.mapping.edit_form',
        [
          'migration' => $this->entity->id(),
          'destination' => rawurlencode($plugin->getDestinationKey()),
        ]
      ),
    ];
    $operations['delete'] = [
      'title' => $this->t('Delete'),
      'url' => new Url(
        'entity.migration.mapping.delete_form',
        [
          'migration' => $this->entity->id(),
          'destination' => rawurlencode($plugin->getDestinationKey()),
        ]
      ),
    ];
    $row['operations'] = [
      '#type' => 'operations',
      '#links' => $operations,
    ];

    return $row;
  }

  /**
   * Builds the summary for a configurable target.
   *
   * @param \Drupal\feeds_migrate\MappingFieldFormInterface $plugin
   *   A mapping field form plugin.
   * @param string $property
   *   A field property to get the summary for.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildSummary(MappingFieldFormInterface $plugin, $property) {
    // Display a summary of the current plugin settings.
    $summary = $plugin->getSummary($property);
    if (!empty($summary)) {
      foreach ($summary as $item) {
        $items = [];
        if (is_array($item)) {
          $items[] = [
            '#type' => 'inline_template',
            '#template' => '<span class="plugin-summary">{{ summary|safe_join("<br />") }}</span>',
            '#context' => ['summary' => $item],
          ];
        }
        else {
          $items[] = $item;
        }
      }

      return [
        '#theme' => 'item_list',
        '#items' => $items,
      ];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\feeds_migrate\Entity\MigrationInterface $migration */
    $migration = $this->entity;
    $mappings = $migration->getMappings();

    // Make sure the reordered mapping keys match the existing mapping keys.
    $form_mappings = $form_state->cleanValues()->getValue('mappings') ?: [];
    $sorted_mappings = $this->sortMappings($form_mappings);

    if (array_diff_key($sorted_mappings, $mappings)) {
      $form_state->setError($form['mappings'], $this->t('The mapping properties have been altered. Please try again.'));
    }

    if ($form_state->hasAnyErrors()) {
      return;
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Write mappings to the migration.
    $mappings = $entity->getMappings();

    // Get the sorted mappings and sort them by weight.
    $form_mappings = $form_state->getValue('mappings') ?: [];
    $sorted_mappings = $this->sortMappings($form_mappings);

    // Make sure mappings get sorted first.
    $mappings = array_merge(array_fill_keys(array_keys($sorted_mappings), []), $mappings);
    // And now merge them.
    $mappings = NestedArray::mergeDeep($mappings, $sorted_mappings);

    // And set them on the entity.
    $entity->setMappings($mappings);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    // If we edited an existing mapping.
    $this->messenger()->addMessage($this->t('Migration mapping for migration @migration has been updated.', [
      '@migration' => $this->getEntity()->label(),
    ]));
  }

  /****************************************************************************/
  // Helper functions.
  /****************************************************************************/

  /**
   * Sorts mappings by weight and splits them.
   *
   * @param array $form_mappings
   *   The mappings as posted on the form.
   *
   * @return array
   *   The sorted mappings.
   */
  protected function sortMappings(array $form_mappings) {
    uasort($form_mappings, [SortArray::class, 'sortByWeightElement']);

    // Split them by key/value.
    $mappings = [];
    foreach ($form_mappings as $key => $values) {
      $property_name = NULL;
      if (strpos($key, '/')) {
        list($target, $property_name) = explode('/', $key);
      }

      if ($property_name) {
        $mappings[$target][$property_name] = $values;
      }
      else {
        $mappings[$key] = $values;
      }
    }

    return $mappings;
  }

  /**
   * Returns the mapping field form plugin for the given field.
   *
   * @param string $field_name
   *   The destination field name.
   * @param array $configuration
   *   Configuration for the plugin.
   *
   * @return \Drupal\feeds_migrate\MappingFieldFormInterface
   *   A mapping field form plugin.
   */
  protected function getMappingFieldFormPlugin($field_name, array $configuration) {
    $plugin_id = $this->mappingFieldManager->getPluginIdFromField($this->entity->getDestinationField($field_name));
    return $this->mappingFieldManager->createInstance($plugin_id, $configuration, $this->entity);
  }

}
