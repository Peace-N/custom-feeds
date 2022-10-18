<?php

namespace Drupal\customfeeds\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\customfeeds\Entity\MigrationInterface;
use Drupal\customfeeds\Exception\MigrateFormPluginNotFoundException;

/**
 * Plugin manager for MigrateForm plugins for process plugins.
 *
 * @package Drupal\customfeeds
 */
class MigrateFormProcessPluginManager extends MigrateFormPluginManager implements MigrateFormProcessPluginManagerInterface {

  use StringTranslationTrait;

  /**
   * The Migrate process plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $processPluginManager;

  /**
   * The form factory.
   *
   * @var \Drupal\customfeeds\Plugin\MigrateFormPluginFactory
   */
  protected $formFactory;

  /**
   * Constructs a new MigrateFormProcessPluginManager object.
   *
   * @param string $type
   *   The plugin type, for example data_parser, data_fetcher, destination...
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $process_plugin_manager
   *   The plugin manager for migrate process plugins.
   * @param \Drupal\customfeeds\Plugin\MigrateFormPluginFactory $form_factory
   *   The factory for feeds migrate form plugins.
   */
  public function __construct($type, \Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, PluginManagerInterface $process_plugin_manager, MigrateFormPluginFactory $form_factory) {
    parent::__construct($type, $namespaces, $cache_backend, $module_handler);
    $this->processPluginManager = $process_plugin_manager;
    $this->formFactory = $form_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessPlugins() {
    $plugins = [];
    foreach ($this->processPluginManager->getDefinitions() as $id => $definition) {
      if (!isset($definition['customfeeds']['form']['configuration'])) {
        // Only include process plugins which have a configuration form.
        continue;
      }

      $category = $definition['category'] ?? (string) $this->t('Other');
      $plugins[$category][$id] = $definition['label'] ?? $id;
    }

    // Don't display plugins in categories if there's only one.
    if (count($plugins) === 1) {
      $plugins = reset($plugins);
    }
    else {
      // Sort categories.
      ksort($plugins);
    }

    return $plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMigrateFormPlugin($plugin_id, MigrationInterface $migration, array $configuration = []) {
    /** @var \Drupal\migrate\Plugin\MigrateProcessInterface $plugin */
    $plugin = $this->processPluginManager->createInstance($plugin_id, $configuration);

    // Mapping only happens during configuration.
    $operation = MigrateFormPluginInterface::FORM_TYPE_CONFIGURATION;
    if (!$this->formFactory->hasForm($plugin, $operation)) {
      throw new MigrateFormPluginNotFoundException();
    }

    return $this->formFactory->createInstance($plugin, $operation, $migration, $configuration);
  }

}
