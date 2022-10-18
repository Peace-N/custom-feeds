<?php

namespace Drupal\customfeeds\Entity;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\migrate_plus\Entity\Migration as MigratePlusMigration;

/**
 * Extends Migrate Plus's migration entity class with extra methods.
 */
class Migration extends MigratePlusMigration implements MigrationInterface {

  /**
   * Field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The list of source to target mappings.
   *
   * @var array
   */
  protected $mappings;

  /**
   * {@inheritdoc}
   */
  public function getDestinationConfiguration() {
    return $this->destination;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceConfiguration() {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeIdFromDestination() {
    if (isset($this->destination['plugin'])) {
      $destination = $this->destination['plugin'];
      if (strpos($destination, ':') !== FALSE) {
        list(, $entity_type) = explode(':', $destination);
        return $entity_type;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundleFromDestination() {
    if (!empty($this->destination['default_bundle'])) {
      return $this->destination['default_bundle'];
    }
    elseif (!empty($this->source['constants']['bundle'])) {
      return $this->source['constants']['bundle'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationField($field_name) {
    $field_definitions = $this->getDestinationFields();

    return $field_definitions[$field_name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationFields() {
    $entity_type_id = $this->getEntityTypeIdFromDestination();
    $entity_bundle = $this->getEntityBundleFromDestination();

    return $this->getFieldManager()->getFieldDefinitions($entity_type_id, $entity_bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getMappings() {
    if (!isset($this->mappings)) {
      $this->mappings = $this->initializeMappings();
    }

    return $this->mappings;
  }

  /**
   * Initialize mapping field instances based on the migration configuration.
   *
   * @return array
   *   List of migration mappings.
   */
  public function initializeMappings() {
    $process_config = $this->normalizeProcessConfig();
    $mappings = [];
    $mapping_dictionary = [];

    // Store a unique list of mapping field instances.
    foreach ($process_config as $destination => $process) {
      $destination_field_name = $process['destination']['key'];

      if (!isset($mapping_dictionary[$destination_field_name])) {
        // We aggregate mapping for fields with multiple field properties.
        $destination_field = $this->getDestinationField($destination_field_name);
        if (isset($destination_field)) {
          $mapping = [];
          $properties = $destination_field->getFieldStorageDefinition()->getPropertyNames();
          $main_property_name = $destination_field->getFieldStorageDefinition()->getMainPropertyName();

          foreach ($properties as $property_name) {
            $destination_key = implode('/', [
              $destination_field_name,
              $property_name,
            ]);

            if (isset($process_config[$destination_key])) {
              $mapping[$property_name] = $process_config[$destination_key];
            }
            elseif ($property_name == $main_property_name && isset($process_config[$destination_field_name])) {
              $mapping[$property_name] = $process_config[$destination_field_name];
            }
          }

          $mapping_dictionary[$destination_field_name] = $mapping;
        }
        else {
          $mapping_dictionary[$destination_field_name]['value'] = $process;
        }
      }

      $mappings[$destination_field_name] = $mapping_dictionary[$destination_field_name];
    }

    return $mappings;
  }

  /**
   * Normalizes migrate process configuration.
   *
   * Resolves shorthands into a list of plugin configurations and ensures
   * 'get' plugins at the start of the process.
   *
   * @return array
   *   The normalized mapping.
   */
  protected function normalizeProcessConfig() {
    $raw_config = $this->get('process');
    $normalized_config = [];
    foreach ($raw_config as $destination => $process) {
      if (is_string($process)) {
        $process = [
          'plugin' => 'get',
          'source' => $process,
        ];
      }
      if (isset($process['plugin'])) {
        if ($process['plugin'] === 'sub_process') {
          foreach ($process['process'] as $property => $sub_process_line) {
            if (is_string($sub_process_line)) {
              $sub_process_line = [
                'plugin' => 'get',
                'source' => $sub_process_line,
              ];
            }

            $destination = implode('/', [$destination, $property]);
            $sub_process_line['source'] = implode('/', [
              $process['source'],
              $sub_process_line['source'],
            ]);
            $normalized_config[$destination] = $sub_process_line;
          }
        }
        else {
          $process = [$process];
        }
      }

      $configuration = [
        'destination' => $this->processGetDestination($destination),
        'source' => '',
        'process' => [],
      ];

      foreach ($process as $index => $process_line) {
        if (isset($process_line['source'])) {
          $source = $process_line['source'];
          $configuration['source'] = $source;
          if (is_string($source) && isset($this->source['ids'][$source])) {
            $configuration['unique'] = TRUE;
          }
        }
        if (isset($process_line['plugin']) && $process_line['plugin'] != 'get') {
          $configuration['process'][$index] = $process_line;
          unset($configuration['process'][$index]['source']);
        }
      }

      $normalized_config[$destination] = $configuration;
    }

    return $normalized_config;
  }

  /**
   * Determines the destination field and property.
   *
   * Process lines in migrations can just consist of the field name, but it can
   * also be defined as `field/property`.
   * Example: 'body/value' and 'body/text_format' have the same destination
   * field (i.e. body).
   *
   * @param string $destination
   *   The key of the process line, representing the destination.
   */
  protected function processGetDestination($destination) {
    if (strpos($destination, '/') === FALSE) {
      return [
        'key' => $destination,
      ];
    }

    $destination_parts = explode('/', $destination);
    return [
      'key' => $destination_parts[0],
      'property' => $destination_parts[1],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setMapping($destination_key, array $mapping) {
    $this->mappings[$destination_key] = $mapping;

    // Cleanup process lines first.
    unset($this->process[$destination_key]);
    foreach ($this->process as $key => $value) {
      if (strpos($key, $destination_key . '/') === 0) {
        unset($this->process[$key]);
      }
    }

    if (!isset($mapping['destination'])) {
      $main_property_name = NULL;
      if (count($mapping) === 1) {
        $destination_field = $this->getDestinationField($destination_key);
        if (isset($destination_field)) {
          $main_property_name = $destination_field->getFieldStorageDefinition()->getMainPropertyName();
        }
        else {
          $main_property_name = 'value';
        }
      }

      foreach ($mapping as $property_name => $property_mapping) {
        if ($property_name == $main_property_name) {
          $process_key = $destination_key;
        }
        else {
          $process_key = implode('/', [$destination_key, $property_name]);
        }

        $this->process[$process_key] = $this->convertMappingToProcess($property_mapping);
      }
    }
    else {
      $this->process[$destination_key] = $this->convertMappingToProcess($mapping);
    }

    return $this;
  }

  /**
   * Converts the given mapping to a process line.
   *
   * @param array $mapping
   *   The mapping to convert.
   */
  protected function convertMappingToProcess(array $mapping) {
    $process = [];

    if (empty($mapping['process'])) {
      $process['plugin'] = 'get';
    }
    else {
      if (count($mapping['process']) == 1) {
        $process = reset($mapping['process']);
      }
      else {
        $process = $mapping['process'];
      }
    }
    if (isset($mapping['source']) && $mapping['source'] !== '' && $mapping['source'] !== NULL) {
      // Set the source on the first process plugin. If there's only one process
      // plugin, then $process is expected to be a singular array. Otherwise, it
      // is expected to be a multidimensional array and in that case 'source' is
      // set one level deeper on the array.
      if (count($mapping['process']) > 1) {
        $process[0]['source'] = $mapping['source'];
      }
      else {
        $process['source'] = $mapping['source'];
      }
    }

    // Simplify process line if possible. If the process line just only contains
    // a source and is of type 'get', we can save it as target => source instead
    // of target => ['plugin' => get, 'source' => source].
    if (isset($process['source'])) {
      if ($process == ['plugin' => 'get', 'source' => $process['source']]) {
        $process = $process['source'];
      }
    }

    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function setMappings(array $mappings) {
    $this->removeMappings();
    foreach ($mappings as $destination_key => $mapping) {
      $this->setMapping($destination_key, $mapping);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeMapping($destination_key) {
    unset($this->mappings[$destination_key]);

    // If the destination key has a match in our mapping array, delete it
    // immediately.
    if (isset($this->process[$destination_key])) {
      unset($this->process[$destination_key]);
    }
    else {
      // An immediate match was not found. Try using the the destination key
      // as a field name to delete all field_name/property mappings.
      $destination_field = $this->getDestinationField($destination_key);
      if (isset($destination_field)) {
        $field_name = $destination_field->getName();
        $properties = $destination_field->getFieldStorageDefinition()->getPropertyNames();

        foreach ($properties as $property_name) {
          $destination_key = implode('/', [$field_name, $property_name]);
          if (isset($this->process[$destination_key])) {
            unset($this->process[$destination_key]);
          }
        }
      }
      else {
        // There is a custom property to remove. Remove all key/subkey
        // instances.
        foreach ($this->process as $key => $value) {
          if (strpos($key, $destination_key . '/') === 0) {
            unset($this->process[$key]);
          }
        }
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeMappings() {
    // We can't just empty arrays here, as I don't know yet if the process array
    // can contain something else beside mappings. -- MegaChriz, 2020-04-13.
    foreach ($this->getMappings() as $destination_key => $mapping) {
      $this->removeMapping($destination_key);
    }

    return $this;
  }

  /**
   * Gets the field manager service.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The field manager.
   */
  protected function getFieldManager() {
    if (!$this->fieldManager) {
      $this->setFieldManager($this->container()->get('entity_field.manager'));
    }
    return $this->fieldManager;
  }

  /**
   * Sets the field manager.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The field manager.
   *
   * @return $this
   */
  public function setFieldManager(EntityFieldManagerInterface $field_manager) {
    $this->fieldManager = $field_manager;
    return $this;
  }

  /**
   * Returns the service container.
   *
   * This method is marked private to prevent sub-classes from retrieving
   * services from the container through it. Instead,
   * \Drupal\Core\DependencyInjection\ContainerInjectionInterface should be used
   * for injecting services.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The service container.
   */
  private function container() {
    return \Drupal::getContainer();
  }

}
