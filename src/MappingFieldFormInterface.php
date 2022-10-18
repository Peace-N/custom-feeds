<?php

namespace Drupal\customfeeds;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface FeedsMigrateUiFieldInterface.
 *
 * @package Drupal\feeds_migrate
 */
interface MappingFieldFormInterface extends PluginInspectionInterface, PluginFormInterface, ConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * Gets this plugin's configuration.
   *
   * @param string $property
   *   The field property to get the configuraiton for.
   *
   * @return array
   *   An array of this plugin's configuration.
   */
  public function getConfiguration($property = NULL);

  /**
   * Get the destination key for a mapping field.
   *
   * @return string
   *   Destination key of the mapping field.
   */
  public function getDestinationKey();

  /**
   * Get the label about a mapping field.
   *
   * @param string $property
   *   The field property to get the process plugin label for.
   *
   * @return string
   *   Text representation of the destination.
   */
  public function getLabel($property = NULL);

  /**
   * Returns a short summary about a mapping field's configuration.
   *
   * If an empty result is returned, a UI can still be provided to display
   * a settings form in case the formatter has configurable settings.
   *
   * @param string $property
   *   A field property to get the summary for.
   *
   * @return string[]
   *   A short summary of the mapping field configuration.
   */
  public function getSummary($property = NULL): array;

  /**
   * Retrieves processing information about the property from $form_state.
   *
   * This method is static so that it can be used in static Form API callbacks.
   *
   * @param string $property
   *   The property to get the field state for.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array with the following key/value pairs:
   *   - plugins: The selected process plugins for the property.
   */
  public static function getPropertyState($property, FormStateInterface $form_state);

  /**
   * Stores processing information about the property in $form_state.
   *
   * This method is static so that it can be used in static Form API #callbacks.
   *
   * @param string $property
   *   The property to set the field state for.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $field_state
   *   The array of data to store. See getPropertyState() for the structure and
   *   content of the array.
   */
  public static function setPropertyState($property, FormStateInterface $form_state, array $field_state);

}
