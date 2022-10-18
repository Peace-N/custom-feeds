<?php

namespace Drupal\customfeeds;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\customfeeds\Entity\MigrationInterface;

/**
 * Interface for the plugin manager that manages MappingFieldForm plugins.
 *
 * @package Drupal\customfeeds
 */
interface MappingFieldFormManagerInterface {

  /**
   * Get the plugin ID from the field type.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The destination field definition.
   *
   * @return \Drupal\customfeeds\MappingFieldFormInterface
   *   The plugin id.
   */
  public function getPluginIdFromField(FieldDefinitionInterface $field = NULL);

  /**
   * Creates a pre-configured instance of a migration plugin.
   *
   * A specific createInstance method is necessary to pass the migration on.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   * @param \Drupal\customfeeds\Entity\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   *
   * @return \Drupal\customfeeds\MappingFieldFormInterface
   *   A fully configured plugin instance.
   */
  public function createInstance($plugin_id, array $configuration = [], MigrationInterface $migration = NULL);

}
