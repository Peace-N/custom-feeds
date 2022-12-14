<?php

namespace Drupal\feeds_migrate\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a migration field mapping annotation object.
 *
 * @see \Drupal\customfeeds\MappingFieldFormBase
 * @see \Drupal\customfeeds\MappingFieldFormInterface
 * @see \Drupal\customfeeds\MappingFieldFormManager
 * @see plugin_api
 *
 * @Annotation
 */
class MappingFieldForm extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The title of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * Fill this out.
   *
   * @var array
   */
  public $fields;

  /**
   * Fill this out.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  public $field;

}
