<?php

namespace Drupal\customfeeds\Entity;

use Drupal\migrate_plus\Entity\MigrationInterface as MigratePlusMigrationInterface;

/**
 * Extends Migrate Plus's migration interface with extra methods.
 */
interface MigrationInterface extends MigratePlusMigrationInterface {

  /**
   * Get the destination configuration.
   *
   * @return array
   *   The destination configuration.
   */
  public function getDestinationConfiguration();

  /**
   * Get the source configuration.
   *
   * @return array
   *   The source configuration.
   */
  public function getSourceConfiguration();

  /**
   * Find the entity type this migration will import into.
   *
   * @return string
   *   Machine name of the entity type eg 'node'.
   */
  public function getEntityTypeIdFromDestination();

  /**
   * The bundle the migration is importing into.
   *
   * @return string
   *   Entity type bundle eg 'article'.
   */
  public function getEntityBundleFromDestination();

  /**
   * Find the field this migration mapping is pointing to.
   *
   * @param string $field_name
   *   The name of the field to look for.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition - if any.
   */
  public function getDestinationField($field_name);

  /**
   * Get a list of fields for the destination the this migration is pointing at.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The array of field definitions for the bundle, keyed by field name.
   */
  public function getDestinationFields();

  /**
   * Gets all migration mappings.
   *
   * @return array
   *   List of migration mappings.
   */
  public function getMappings();

  /**
   * Sets a single migration mapping.
   *
   * @param string $destination_key
   *   The mapping to set.
   * @param array $mapping
   *   A single mapping.
   *
   * @return $this
   *   An instance of this class.
   */
  public function setMapping($destination_key, array $mapping);

  /**
   * Sets all migration mappings.
   *
   * @param array $mappings
   *   A list of mappings.
   *
   * @return $this
   *   An instance of this class.
   */
  public function setMappings(array $mappings);

  /**
   * Removes a mapping from the migration.
   *
   * @param string $destination_key
   *   The mapping to remove.
   *
   * @return $this
   *   An instance of this class.
   */
  public function removeMapping($destination_key);

  /**
   * Removes all mappings.
   *
   * @return $this
   *   An instance of this class.
   */
  public function removeMappings();

}
