<?php

namespace Drupal\customfeeds\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * A Feeds migrate importer.
 *
 * @QueueWorker(
 *   id = "customfeeds_importer",
 *   title = @Translation("Feeds Migrate Importer"),
 *   cron = {"time" = 60}
 * )
 */
class FeedsMigrateImporter extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Manager for entity types.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\customfeeds\FeedsMigrateImporterInterface $customfeeds_importer */
    $customfeeds_importer = $this->entityTypeManager->getStorage('customfeeds_importer')
      ->load($data);
    $migrate_executable = $customfeeds_importer->getExecutable();
    $migrate_executable->import();
  }

}
