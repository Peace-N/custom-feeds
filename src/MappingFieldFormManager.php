<?php

namespace Drupal\customfeeds;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\feeds_migrate\Entity\MigrationInterface;

/**
 * Manages feeds migrate UI field plugins.
 *
 * @see \Drupal\customfeeds\Annotation\FeedsMigrateUiField
 * @see plugin_api
 *
 * @package Drupal\feeds_migrate
 */
class MappingFieldFormManager extends DefaultPluginManager implements MappingFieldFormManagerInterface, FallbackPluginManagerInterface {

  /**
   * Constructs a new FeedsMigrateUiFieldManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/feeds_migrate/mapping_field',
      $namespaces,
      $module_handler,
      'Drupal\feeds_migrate\MappingFieldFormInterface',
      'Drupal\feeds_migrate\Annotation\MappingFieldForm');

    $this->alterInfo('feeds_migrate_mapping_field_form_info');
    $this->setCacheBackend($cache_backend, 'feeds_migrate_mapping_field_form');
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    return 'default';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginIdFromField(FieldDefinitionInterface $field = NULL) {
    if (!empty($field)) {
      $definitions = $this->getDefinitions();
      foreach ($definitions as $plugin_id => $definition) {
        if (in_array($field->getType(), $definition['fields'])) {
          return $plugin_id;
        }
      }
    }

    return $this->getFallbackPluginId(NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = [], MigrationInterface $migration = NULL) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);

    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      $plugin = $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition, $migration);
    }
    else {
      $plugin = new $plugin_class($configuration, $plugin_id, $plugin_definition, $migration);
    }
    return $plugin;
  }

}
