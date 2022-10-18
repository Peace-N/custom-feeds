<?php

namespace Drupal\feeds_migrate_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\customfeeds\Entity\MigrationInterface;

/**
 * Returns responses for feeds_migrate_ui routes.
 */
class FeedsMigrateController extends ControllerBase {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new feeds migrate controller.
   */
  public function __construct() {
    $this->logger = $this->getLogger('feeds_migrate');
  }

  /**
   * Loads the entity form for editing a mapping.
   *
   * @param \Drupal\customfeeds\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param string $destination
   *   The destination field name or key.
   *
   * @return array
   *   The loaded entity form.
   */
  public function mappingEditForm(MigrationInterface $migration, $destination = NULL) {
    $operation = 'mapping-edit';

    $entity_form = $this->entityTypeManager()->getFormObject($migration->getEntityTypeId(), $operation);
    $entity_form->setEntity($migration);
    $mapping = $migration->getMappings()[$destination] ?? [];
    $entity_form->setMapping($destination, $mapping);

    return $this->formBuilder()->getForm($entity_form);
  }

  /**
   * Loads the entity form for deleting a mapping.
   *
   * @param \Drupal\customfeeds\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param string $destination
   *   The destination field name or key.
   *
   * @return array
   *   The loaded entity form.
   */
  public function mappingDeleteForm(MigrationInterface $migration, $destination = NULL) {
    $operation = 'mapping-delete';

    $entity_form = $this->entityTypeManager()->getFormObject($migration->getEntityTypeId(), $operation);
    $entity_form->setEntity($migration);
    $entity_form->setDestinationKey($destination);

    return $this->formBuilder()->getForm($entity_form);
  }

}
