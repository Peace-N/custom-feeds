<?php

namespace Drupal\customfeeds\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\customfeeds\Entity\MigrationInterface;

/**
 * Interface for managing form plugins for process plugins.
 *
 * @package Drupal\customfeeds
 */
interface MigrateFormProcessPluginManagerInterface extends PluginManagerInterface {

  /**
   * Returns a list of available process plugins with a configuration form.
   *
   * @return array
   *   List of process plugins, keyed by plugin id.
   */
  public function getProcessPlugins();

  /**
   * Loads the process plugin.
   *
   * @param string $plugin_id
   *   The id of the process plugin.
   * @param \Drupal\customfeeds\Entity\MigrationInterface $migration
   *   The migration to load a form plugin for.
   * @param array $configuration
   *   The configuration for the process plugin.
   *
   * @return \Drupal\customfeeds\Plugin\MigrateFormPluginInterface
   *   The form process plugin instance.
   *
   * @throws \Drupal\customfeeds\Exception\MigrateFormPluginNotFoundException
   *   In case no form exists for the specified process plugin ID.
   */
  public function loadMigrateFormPlugin($plugin_id, MigrationInterface $migration, array $configuration = []);

}
