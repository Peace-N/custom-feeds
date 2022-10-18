<?php

namespace Drupal\customfeeds;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface;
use Drupal\feeds_migrate\Plugin\MigrateFormProcessPluginManagerInterface;
use Drupal\feeds_migrate\Entity\MigrationInterface;
use Drupal\feeds_migrate\Exception\MigrateFormPluginNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for mapping fields.
 *
 * @package Drupal\feeds_migrate
 */
abstract class MappingFieldFormBase extends PluginBase implements MappingFieldFormInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The migration.
   *
   * @var \Drupal\customfeeds\Entity\MigrationInterface
   */
  protected $migration;

  /**
   * Field Type Manager Service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The Migrate process plugin form manager.
   *
   * @var \Drupal\customfeeds\Plugin\MigrateFormProcessPluginManagerInterface
   */
  protected $processPluginFormManager;

  /**
   * The destination key.
   *
   * This is filled out when we are not migrating into a standard Drupal field
   * instance (e.g. table column name, virtual field etc...)
   *
   * @var string
   */
  protected $destinationKey;

  /**
   * Constructs a mapping field form base.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\feeds_migrate\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The manager for field types.
   * @param \Drupal\feeds_migrate\Plugin\MigrateFormProcessPluginManagerInterface $process_plugin_form_manager
   *   The manager for process plugin forms.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, FieldTypePluginManagerInterface $field_type_manager, MigrateFormProcessPluginManagerInterface $process_plugin_form_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migration = $migration;
    $this->fieldTypeManager = $field_type_manager;
    $this->processPluginFormManager = $process_plugin_form_manager;

    // Set some properties.
    $this->destinationKey = $configuration['destination_key'] ?? NULL;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('plugin.manager.field.field_type'),
      $container->get('plugin.manager.feeds_migrate.migrate.process_form')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = [
      'destination_key' => $this->destinationKey,
      'mapping' => [],
    ];

    $default_mapping_config = [
      'destination' => ['key' => ''],
      'source' => '',
      'unique' => FALSE,
      'process' => [],
    ];

    if (!isset($this->destinationKey) || !isset($this->migration)) {
      // No destination key or no migration known. Abort.
      return [];
    }

    // @todo replace with TargetDefinitionInterface?
    $field = $this->migration->getDestinationField($this->destinationKey);
    if ($field) {
      foreach ($this->getFieldProperties($field) as $property => $info) {
        $config['mapping'][$property] = $default_mapping_config;
        $config['mapping'][$property]['destination']['key'] = $this->destinationKey;
        $config['mapping'][$property]['destination']['property'] = $property;
      }
    }
    else {
      $config['mapping']['value'] = $default_mapping_config;
      $config['mapping']['value']['destination']['key'] = $this->destinationKey;
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration($property = NULL) {
    if (!isset($property)) {
      return $this->configuration;
    }

    return $this->configuration['mapping'][$property];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel($property = NULL) {
    $field = $this->migration->getDestinationField($this->destinationKey);
    $label = isset($field) ? $field->getLabel() : $this->destinationKey;

    if ($property) {
      $label .= ' (' . $property . ')';
    }

    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary($property = 'value'): array {
    $summary = [];
    $process = $this->configuration['mapping'][$property]['process'] ?? [];

    foreach ($process as $info) {
      $plugin_id = $info['plugin'];
      $plugin = $this->loadMigrateFormPlugin($plugin_id, $info);
      if ($plugin) {
        $plugin_summary = $plugin->getSummary();
        if (!empty($plugin_summary)) {
          $summary[] = $plugin_summary;
        }
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationKey() {
    return $this->destinationKey;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $mapping = $this->configuration['mapping'];
    $field = $this->migration->getDestinationField($this->destinationKey);

    $elements = [];

    // If the field has one or more properties, iterate over them and render
    // a mapping form.
    if (isset($field)) {
      /** @var \Drupal\Core\TypedData\TypedDataInterface[] $field_properties */
      $field_properties = $this->getFieldProperties($field);
      foreach ($field_properties as $property => $info) {
        $elements['properties'][$property] = [
          '#tree' => TRUE,
        ];
        $elements['properties'][$property] = [
          '#title' => $this->t('Mapping for field property %property.', ['%property' => $info->getName()]),
          '#type' => 'details',
          '#group' => 'plugin_settings',
          '#open' => TRUE,
        ];

        $elements['properties'][$property]['source'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Source'),
          '#default_value' => $mapping[$property]['source'] ?? '',
        ];

        $elements['properties'][$property]['unique'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Unique'),
          '#default_value' => $mapping[$property]['unique'] ?? FALSE,
        ];

        $elements['properties'][$property] += $this->buildProcessPluginsConfigurationForm([], $form_state, $property);
      }
    }
    else {
      $elements = [
        '#title' => $this->t('Mapping for %field.', ['%field' => $this->getLabel()]),
        '#type' => 'details',
        '#group' => 'plugin_settings',
        '#open' => TRUE,
      ];

      $elements['source'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Source'),
        '#default_value' => $mapping['value']['source'] ?? '',
      ];

      $elements['unique'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Unique Field'),
        '#default_value' => $mapping['value']['unique'] ?? FALSE,
      ];

      $elements += $this->buildProcessPluginsConfigurationForm([], $form_state);
    }

    return $elements;
  }

  /**
   * Builds the form for configuring process plugins for a single property.
   *
   * Every field (property) can add one or many migration process plugins to
   * prepare the data before it is stored.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $property
   *   The field property to render the process plugin table for.
   *
   * @return array
   *   The form structure.
   */
  protected function buildProcessPluginsConfigurationForm(array $form, FormStateInterface $form_state, $property = NULL) {
    // Generate a unique HTML id for AJAX callback.
    $ajax_id = implode('-', [
      'feeds-migration-mapping',
      $property,
      'ajax-wrapper',
    ]);
    // Declare AJAX settings for process plugin table.
    $ajax_settings = [
      'event' => 'click',
      'effect' => 'fade',
      'progress' => 'throbber',
      'callback' => [get_class($this), 'ajaxCallback'],
      'wrapper' => $ajax_id,
    ];
    // Load process plugins from configuration or form state.
    $plugins = $this->loadProcessPlugins($form_state, $property);

    $form['process'] = [
      '#type' => 'container',
      '#prefix' => "<div id='$ajax_id'>",
      '#suffix' => "</div>",
      '#property_name' => $property,
    ];

    // The process plugin table, with config forms for each instance.
    $form['process']['plugins'] = [
      '#tree' => TRUE,
      '#type' => 'table',
      '#header' => [
        $this->t('Plugin'),
        $this->t('Configuration'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
      '#empty' => $this->t('No process plugins have been added yet.'),
      '#default_value' => [],
    ];

    // Selector for adding new process plugin instances.
    $form['process']['add']['plugin'] = [
      '#type' => 'select',
      '#options' => $this->processPluginFormManager->getProcessPlugins(),
      '#empty_option' => $this->t('- Select a process plugin -'),
      '#default_value' => NULL,
    ];

    $form['process']['add']['button'] = [
      '#name' => implode('-', ['feeds-migration-mapping-', $property]),
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#context' => [
        'action' => 'add',
      ],
      '#limit_validation_errors' => [],
      '#submit' => [[get_class($this), 'addProcessPluginSubmit']],
      '#ajax' => $ajax_settings,
    ];

    // Build out table.
    foreach ($plugins as $delta => $configuration) {
      $plugin_id = $configuration['plugin'];
      $plugin = $this->loadMigrateFormPlugin($plugin_id, $configuration);

      if ($plugin) {
        $form['process']['plugins'][$delta] = $this->buildProcessRow($form, $form_state, $plugin, $delta, $property);
      }
    }

    return $form;
  }

  /**
   * Builds a single process plugin row.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface $plugin
   *   The migrate process plugin.
   * @param int $delta
   *   The index number of the process plugin.
   * @param string $property
   *   The field property for this mapping.
   *
   * @return array
   *   The built table row.
   */
  protected function buildProcessRow(array $form, FormStateInterface $form_state, MigrateFormPluginInterface $plugin, $delta, $property = NULL) {
    $plugin_id = $plugin->getPluginId();
    $configuration = $plugin->getConfiguration();

    // Generate a unique HTML id for AJAX callback.
    $ajax_id = implode('-', [
      'feeds-migration-mapping',
      $property,
      'ajax-wrapper',
    ]);
    // Declare AJAX settings for process plugin table.
    $ajax_settings = [
      'event' => 'click',
      'effect' => 'fade',
      'progress' => 'throbber',
      'callback' => [get_class($this), 'ajaxCallback'],
      'wrapper' => $ajax_id,
    ];

    $row = [
      '#attributes' => [
        'class' => ['draggable'],
      ],
      'label' => [
        '#type' => 'label',
        '#title' => $plugin->getPluginDefinition()['title'],
      ],
      'configuration' => [
        '#type' => 'container',
      ],
      'weight' => [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @process_name', [
          '@process_name' => $plugin_id,
        ]),
        '#title_display' => 'invisible',
        '#default_value' => $delta,
        '#attributes' => [
          'class' => [
            'table-sort-weight',
          ],
        ],
      ],
      'operations' => [
        '#type' => 'submit',
        // We need a unique element name so we can reliably use
        // $form_state->getTriggeringElement() in the submit callbacks.
        '#name' => implode(',', [
          'feeds-migration-mapping-',
          $property,
          $delta,
        ]),
        '#value' => $this->t('Remove'),
        '#limit_validation_errors' => [],
        '#submit' => [[get_class($this), 'removeProcessPluginSubmit']],
        '#context' => [
          'action' => 'remove',
          'delta' => $delta,
        ],
        '#ajax' => $ajax_settings,
      ],
    ];

    // Load process form plugin configuration.
    $plugin_form_state = SubformState::createForSubform($row['configuration'], $form, $form_state);
    $row['configuration']['plugin'] = [
      '#type' => 'hidden',
      '#value' => $configuration['plugin'],
    ];
    $row['configuration'] += $plugin->buildConfigurationForm([], $plugin_form_state);

    return $row;
  }

  /****************************************************************************/
  // Callbacks.
  /****************************************************************************/

  /**
   * The form submit callback for adding a new column.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function addProcessPluginSubmit(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();

    // Go two levels up in the form, to the process container.
    $process_form = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -2));
    $plugins = $process_form['plugins'];
    $values = $plugins['#value'];

    // Add new plugin id.
    $values[] = [
      'configuration' => [
        'plugin' => $process_form['add']['plugin']['#value'],
      ],
    ];

    // Update plugin's #value.
    $form_state->setValueForElement($plugins, $values);
    NestedArray::setValue($form_state->getUserInput(), $plugins['#parents'], $values);

    // Store selected process plugins.
    $field_state = static::getPropertyState($process_form['#property_name'], $form_state);
    $field_state['plugins'] = $values;
    static::setPropertyState($process_form['#property_name'], $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * The form submit callback for removing a column.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function removeProcessPluginSubmit(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $delta = $button['#context']['delta'];
    $process_form = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -3));
    $plugins = $process_form['plugins'];
    $values = $plugins['#value'];

    // Remove plugin from values.
    unset($values[$delta]);
    // Re-index values.
    $values = array_values($values);

    // Update plugin's #value.
    $form_state->setValueForElement($plugins, $values);
    NestedArray::setValue($form_state->getUserInput(), $plugins['#parents'], $values);

    // Store selected process plugins.
    $field_state = static::getPropertyState($process_form['#property_name'], $form_state);
    $field_state['plugins'] = $values;
    static::setPropertyState($process_form['#property_name'], $form_state, $field_state);

    $form_state->setRebuild(TRUE);
  }

  /**
   * The form ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form element to return.
   */
  public static function ajaxCallback(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $action = $button['#context']['action'] ?? NULL;
    $parent_offset = $action === 'remove' ? -3 : -2;

    return NestedArray::getValue($form, array_slice($button['#array_parents'], 0, $parent_offset));
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Check if any of the properties have either a mapping source or a process
    // plugin.
    $has_config = FALSE;
    $config_values = $values['properties'] ?? [$values];
    foreach ($config_values as $property => $mapping) {
      if (strlen($mapping['source'])) {
        // One of the properties has a source.
        $has_config = TRUE;
        break;
      }
      if (!empty($mapping['process']['plugins'])) {
        // One of the properties has process plugin configuration.
        $has_config = TRUE;
        break;
      }
    }
    if (!$has_config) {
      $element = $form['properties'] ?? $form;
      if (count($config_values) === 1) {
        $message = $this->t('Please enter a source or configure a process plugin.');
      }
      else {
        $message = $this->t('Please enter a source or configure a process plugin for at least one of the properties.');
      }
      $form_state->setError($element, $message);
    }

    if (isset($values['properties'])) {
      // Validate process plugin configuration.
      foreach ($values['properties'] as $property => $mapping) {
        // Skip properties that don't support process plugins.
        if (empty($mapping['process']['plugins'])) {
          continue;
        }

        $this->validateProcessPlugins($form, $form_state, $form['properties'][$property]['process']['plugins'], $mapping['process']['plugins']);
      }
    }
    elseif (isset($values['process']['plugins'])) {
      $this->validateProcessPlugins($form, $form_state, $form['process']['plugins'], $values['process']['plugins']);
    }
  }

  /**
   * Validates all process plugin configuration.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $subforms
   *   The form fields for the process plugin configuration.
   * @param array $process_plugins
   *   The submitted process plugin configuration values.
   */
  protected function validateProcessPlugins(array &$form, FormStateInterface $form_state, array &$subforms, array $process_plugins) {
    foreach ($process_plugins as $delta => $info) {
      // Load migrate process plugin.
      $plugin_id = $info['configuration']['plugin'];
      $plugin = $this->loadMigrateFormPlugin($plugin_id);

      $subform = &$subforms[$delta]['configuration'];
      if ($form_state instanceof SubFormStateInterface) {
        // When two subforms are chained in each other,
        // SubFormState::setErrorByName() uses the #array_parents from both the
        // parent form and subform, this results into the #array_parents from
        // the parent form being reused to construct the field form name. The
        // constructed form field name then for example becomes:
        // @code
        //   mapping][title][mapping][title][properties][value][process][plugins][0][configuration][find
        // @endcode
        // So "mapping/title" is duplicated above.
        // To fix this we remove the #array_parents elements from subform that
        // already part of the parent form's #array_parents.
        $subform['#array_parents'] = array_diff($subform['#array_parents'], $form['#array_parents']);
      }

      // Find the plugin's form.
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      // Have the plugin validate its configuration.
      $plugin->validateConfigurationForm($subform, $subform_state);

      $plugin_errors = $subform_state->getErrors();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (!isset($values['properties'])) {
      $values['properties']['value'] = $values;
    }

    foreach ($values['properties'] as $property => $mapping) {
      // Remove mapping if source and process are empty.
      if (strlen($mapping['source']) === 0 && empty($mapping['process']['plugins'])) {
        unset($this->configuration['mapping'][$property]);
        continue;
      }

      $this->configuration['mapping'][$property]['source'] = $mapping['source'];
      $this->configuration['mapping'][$property]['unique'] = $mapping['unique'];

      // Skip properties that don't support process plugins.
      if (empty($mapping['process']['plugins'])) {
        $mapping['process']['plugins'] = [];
        continue;
      }

      if (isset($form['properties'][$property]['process']['plugins'])) {
        $plugins = $form['properties'][$property]['process']['plugins'];
      }
      else {
        $plugins = $form['process']['plugins'];
      }

      $this->configuration['mapping'][$property]['process'] = $this->submitProcessPlugins($form, $form_state, $plugins, $mapping['process']['plugins']);
    }
  }

  /**
   * Submits all process plugin configuration.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $subforms
   *   The form fields for the process plugin configuration.
   * @param array $process_plugins
   *   The submitted process plugin configuration values.
   *
   * @return array
   *   Configuration for all process plugins.
   */
  protected function submitProcessPlugins(array &$form, FormStateInterface $form_state, array &$subforms, array $process_plugins) {
    $process = [];

    foreach ($process_plugins as $delta => $info) {
      // Load migrate process plugin.
      $plugin_id = $info['configuration']['plugin'];
      $plugin = $this->loadMigrateFormPlugin($plugin_id);

      // Find the plugin's form.
      $subform = &$subforms[$delta]['configuration'];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      // Have the plugin save its configuration.
      $plugin->submitConfigurationForm($subform, $subform_state);

      // Retrieve the plugin configuration.
      $process_configuration = $plugin->getConfiguration();
      $process[] = $process_configuration;
    }

    return $process;
  }

  /**
   * Retrieve all field properties that are not calculated.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The field definition to load the properties for.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface[]
   *   An array of property objects implementing the TypedDataInterface, keyed
   *   by property name.
   */
  protected function getFieldProperties(FieldDefinitionInterface $field) {
    $field_properties = [];

    try {
      $item_instance = $this->fieldTypeManager->createInstance($field->getType(), [
        'name' => NULL,
        'parent' => NULL,
        'field_definition' => $field,
      ]);

      $field_properties = $item_instance->getProperties();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Could not load properties for %field_name.', [
        '%field_name' => $field->getName(),
      ]));
    }

    return $field_properties;
  }

  /****************************************************************************/
  // Helper functions.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public static function getPropertyState($property, FormStateInterface $form_state) {
    return NestedArray::getValue($form_state->getStorage(), [$property]);
  }

  /**
   * {@inheritdoc}
   */
  public static function setPropertyState($property, FormStateInterface $form_state, array $field_state) {
    NestedArray::setValue($form_state->getStorage(), [$property], $field_state);
  }

  /**
   * Load the configured plugins from form_state or save configuration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $property
   *   The name of the field property, if any.
   *
   * @return array
   *   List of process plugin form configuration.
   */
  protected function loadProcessPlugins(FormStateInterface $form_state, $property = NULL) {
    $plugins = [];
    if (!isset($property)) {
      $property = 'value';
    }
    $mapping = $this->configuration['mapping'][$property];
    $form_state_key = array_filter([
      ($property ? 'properties' : ''),
      $property,
      'process',
      'plugins',
    ]);
    $values = $form_state->getValue($form_state_key, $mapping['process']);
    if (empty($values)) {
      // Try storage.
      $field_state = static::getPropertyState($property, $form_state);
      if (!empty($field_state['plugins'])) {
        $values = $field_state['plugins'];
      }
    }

    foreach ($values as $delta => $info) {
      $plugins[] = $info['configuration'] ?? $info;
    }

    return $plugins;
  }

  /**
   * Loads the process plugin.
   *
   * @param string $plugin_id
   *   The id of the process plugin.
   * @param array $configuration
   *   The configuration for the process plugin.
   *
   * @return \Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface|null
   *   The process plugin instance or null in case the process plugin could not
   *   be instantiated.
   */
  protected function loadMigrateFormPlugin($plugin_id, array $configuration = []) {
    try {
      return $this->processPluginFormManager->loadMigrateFormPlugin($plugin_id, $this->migration, $configuration);
    }
    catch (MigrateFormPluginNotFoundException $e) {
      $this->messenger()->addError($this->t('Could not find form plugin for %plugin_id', [
        '%plugin_id' => $plugin_id,
      ]));
    }
    catch (PluginException $e) {
      $this->messenger()->addError($this->t('The specified plugin %plugin_id is invalid.', [
        '%plugin_id' => $plugin_id,
      ]));
    }
  }

}
