<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Url;
use Drupal\feeds_migrate\MappingFieldFormManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base form for migration mapping configuration.
 *
 * @package Drupal\feeds_migrate\Form
 */
class MigrationMappingFormBase extends EntityForm {

  const CUSTOM_DESTINATION_KEY = '_custom';

  /**
   * Plugin manager for migration mapping plugins.
   *
   * @var \Drupal\feeds_migrate\MappingFieldFormManager
   */
  protected $mappingFieldManager;

  /**
   * Manager for entity types.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Manager for entity fields.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

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
   * Field mapping for this migration.
   *
   * @var array
   */
  protected $mapping = [];

  /**
   * Creates a new MigrationMappingFormBase object.
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
   * Gets the label for the destination field - if any.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The label of the destination field, or key if the destination is a custom
   *   property.
   */
  public function getDestinationFieldLabel() {
    $field = $this->entity->getDestinationField($this->destinationKey);
    if ($field) {
      return $field->getLabel();
    }
    return $this->destinationKey;
  }

  /**
   * Sets the mapping for this field.
   *
   * @param string $key
   *   The destination key.
   * @param array $mapping
   *   (optional) The field mapping for this migration.
   */
  public function setMapping($key, array $mapping = []) {
    $this->destinationKey = $key;
    $this->mapping = $mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Set #parents to 'top-level' by default.
    $form += ['#parents' => []];

    // Support AJAX callback.
    $form['#tree'] = FALSE;
    $form['#parents'] = [];
    $form['#prefix'] = '<div id="feeds-migration-mapping-ajax-wrapper">';
    $form['#suffix'] = '</div>';

    // General mapping settings.
    $form['general'] = [
      '#title' => $this->t('General'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => FALSE,
    ];

    // Retrieve a list of mapping field destinations.
    $options = $this->getMappingDestinationOptions();
    asort($options);
    // Allow custom destination keys.
    $options[self::CUSTOM_DESTINATION_KEY] = $this->t('Other...');

    // Determine default value.
    $default_value = NULL;
    if (isset($this->destinationKey)) {
      $default_value = array_key_exists($this->destinationKey, $options) ?
        $this->destinationKey : self::CUSTOM_DESTINATION_KEY;
    }

    $form['general']['destination_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Destination field'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select a destination -'),
      '#default_value' => $default_value,
      '#disabled' => ($this->operation === 'mapping-edit'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_called_class(), 'ajaxCallback'],
        'event' => 'change',
        'wrapper' => 'feeds-migration-mapping-ajax-wrapper',
        'effect' => 'fade',
        'progress' => 'throbber',
      ],
    ];

    $form['general']['destination_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Destination key'),
      '#default_value' => $this->destinationKey,
      '#disabled' => ($this->operation === 'mapping-edit'),
      '#states' => [
        'required' => [
          ':input[name="destination_field"]' => ['value' => self::CUSTOM_DESTINATION_KEY],
        ],
        'visible' => [
          ':input[name="destination_field"]' => ['value' => self::CUSTOM_DESTINATION_KEY],
        ],
      ],
    ];

    // Mapping Field Plugin settings.
    if ($this->destinationKey) {
      // Field specific mapping settings.
      $form['mapping'] = [
        '#parents' => ['mapping'],
        '#type' => 'container',
        '#tree' => TRUE,
        $this->destinationKey => [
          '#parents' => ['mapping', $this->destinationKey],
        ],
      ];

      $destination_field = $this->entity->getDestinationField($this->destinationKey);
      $plugin_id = $this->mappingFieldManager->getPluginIdFromField($destination_field);
      $plugin = $this->mappingFieldManager->createInstance($plugin_id, $this->getPluginConfig(), $this->entity);
      $plugin_form_state = SubformState::createForSubform($form['mapping'][$this->destinationKey], $form, $form_state);

      if ($plugin) {
        $form['mapping'][$this->destinationKey] = $plugin->buildConfigurationForm($form, $plugin_form_state);
      }
    }

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::actions().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Get the basic actions from the base class.
    $actions = parent::actions($form, $form_state);

    // Change the submit button text.
    $actions['submit']['#value'] = $this->t('Save');

    // Change delete url.
    if ($this->operation === 'mapping-edit' && isset($this->destinationKey)) {
      $actions['delete']['#url'] = new Url(
        'entity.migration.mapping.delete_form',
        [
          'migration' => $this->entity->id(),
          'destination' => rawurlencode($this->destinationKey),
        ]
      );
    }
    else {
      unset($actions['delete']);
    }

    // Return the result.
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\feeds_migrate\Entity\MigrationInterface $migration */
    $migration = $this->getEntity();

    // Save the migration.
    $status = $migration->save();

    $field_name = $this->getDestinationFieldLabel();

    if ($status == SAVED_UPDATED) {
      // If we edited an existing mapping.
      $this->messenger()->addMessage($this->t('Migration mapping for field
        %destination_field has been updated.', [
          '%destination_field' => $field_name,
        ]
      ));
    }
    else {
      // If we created a new mapping.
      $this->messenger()->addMessage($this->t('Migration mapping for field
        %destination_field has been added.', [
          '%destination_field' => $field_name,
        ]
      ));
    }

    // Redirect the user to the mapping overview form.
    $form_state->setRedirect('entity.migration.mapping.list', [
      'migration' => $migration->id(),
    ]);
  }

  /****************************************************************************/
  // Callbacks.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_plugin = $this->loadMappingFieldFormPlugin();
    if ($this->destinationKey && $form_plugin) {
      $subform = &$form['mapping'][$this->destinationKey];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $form_plugin->validateConfigurationForm($subform, $subform_state);

      // Stop validation if the element's properties has any errors.
      if ($subform_state->hasAnyErrors()) {
        return;
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Support non-javascript browsers.
    if (!isset($form['mapping'])) {
      $form_state->setRebuild();
      return;
    }

    /** @var \Drupal\feeds_migrate\Entity\MigrationInterface $migration */
    $migration = $this->entity;

    // Get migration configuration(s).
    $source_config = $migration->getSourceConfiguration() ?: [];

    $form_plugin = $this->loadMappingFieldFormPlugin();
    if ($this->destinationKey && $form_plugin) {
      $subform = &$form['mapping'][$this->destinationKey];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $form_plugin->submitConfigurationForm($subform, $subform_state);

      // Retrieve the mapping configuration and save on the migration entity.
      $plugin_configuration = $form_plugin->getConfiguration();
      $destination_key = $plugin_configuration['destination_key'];
      foreach ($plugin_configuration['mapping'] as $destination => $mapping) {
        // We always start with the get plugin to obtain the source value.
        $source = $mapping['source'];

        // Save off field properties in source.
        // @todo This should happen on the Migration entity instead?
        if (!isset($source_config['fields']) || array_search($source, array_column($source_config['fields'], 'name')) === FALSE) {
          $source_config['fields'][] = [
            'name' => $source,
            'label' => $source,
            'selector' => $source,
          ];
        }

        // Handle unique field values.
        // @todo This should happen on the Migration entity instead?
        if ($mapping['unique']) {
          $source_config['ids'][$source] = ['type' => 'string'];
        }
        else {
          unset($source_config['ids'][$source]);
        }
      }

      $migration->setMapping($destination_key, $plugin_configuration['mapping']);
    }

    // @todo logic for setting source should probably not happen here, but on
    // the Migration object.
    $migration->set('source', $source_config);
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
    return $form;
  }

  /****************************************************************************/
  // Helper functions.
  /****************************************************************************/

  /**
   * Returns a list of all mapping destination options, keyed by field name.
   */
  protected function getMappingDestinationOptions() {
    $options = [];

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
    $fields = $this->entity->getDestinationFields();
    foreach ($fields as $field_name => $field) {
      $options[$field->getName()] = $field->getLabel();
    }

    return $options;
  }

  /**
   * Returns the configuration to be passed to the MappingFieldForm plugin.
   *
   * @return array
   *   MappingFieldForm plugin configuration.
   */
  protected function getPluginConfig() {
    return [
      'destination_key' => $this->destinationKey,
      'mapping' => $this->mapping,
    ];
  }

  /**
   * Load mapping field form plugin.
   *
   * @return \Drupal\feeds_migrate\MappingFieldFormInterface
   *   Mapping field form plugin instance.
   */
  protected function loadMappingFieldFormPlugin() {
    /** @var \Drupal\feeds_migrate\Entity\MigrationInterface $migration */
    $migration = $this->entity;
    $destination_field = $mapping['destination']['field'] ?? NULL;
    $plugin_id = $this->mappingFieldManager->getPluginIdFromField($destination_field);

    /** @var \Drupal\feeds_migrate\MappingFieldFormInterface $plugin */
    $form_plugin = $this->mappingFieldManager->createInstance($plugin_id, $this->getPluginConfig(), $migration);
    return $form_plugin;
  }

}
