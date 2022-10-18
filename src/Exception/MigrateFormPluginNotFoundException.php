<?php

namespace Drupal\customfeeds\Exception;

use Drupal\Component\Plugin\Exception\PluginException;

/**
 * Thrown when a form plugin does not exist for a Migrate plugin.
 */
class MigrateFormPluginNotFoundException extends PluginException {}
