<?php

namespace Drupal\customfeeds\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\customfeeds\FeedsMigrateBatchExecutable;
use Drupal\customfeeds\FeedsMigrateExecutable;
use Drupal\customfeeds\FeedsMigrateImporterInterface;
use Drupal\migrate\MigrateMessage;

/**
 * Feeds Migrate Importer configuration entity.
 *
 * @ConfigEntityType(
 *   id = "feeds_migrate_importer",
 *   label = @Translation("Feeds Migrate Importer"),
 *   handlers = {
 *     "list_builder" = "Drupal\customfeeds\FeedsMigrateImporterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\customfeeds\Form\FeedsMigrateImporterForm",
 *       "edit" = "Drupal\customfeeds\Form\FeedsMigrateImporterForm",
 *       "delete" = "Drupal\customfeeds\Form\FeedsMigrateImporterDeleteForm",
 *       "enable" = "Drupal\customfeeds\Form\FeedsMigrateImporterEnableForm",
 *       "disable" = "Drupal\customfeeds\Form\FeedsMigrateImporterDisableForm",
 *       "import" = "Drupal\customfeeds\Form\FeedsMigrateImporterImportForm",
 *       "rollback" = "Drupal\customfeeds\Form\FeedsMigrateImporterRollbackForm"
 *     },
 *   },
 *   config_prefix = "importer",
 *   admin_permission = "administer feeds migrate importers",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "importFrequency",
 *     "existing",
 *     "keepOrphans",
 *     "migrationId",
 *     "migrationConfig"
 *   },
 *   links = {
 *     "canonical" = "/admin/content/feeds-migrate/{feeds_migrate_importer}",
 *     "add-form" = "/admin/content/feeds-migrate/add",
 *     "edit-form" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/edit",
 *     "delete-form" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/delete",
 *     "enable-form" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/enable",
 *     "disable-form" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/disable",
 *     "import-form" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/import",
 *     "rollback-form" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/rollback"
 *   }
 * )
 */
class FeedsMigrateImporter extends ConfigEntityBase implements FeedsMigrateImporterInterface {

  /**
   * The frequency at which this importer should be executed.
   *
   * @var int
   */
  protected $importFrequency;

  /**
   * Indicates how existing content should be processed.
   *
   * @var string
   */
  protected $existing;

  /**
   * Indicates if orphaned content should be kept.
   *
   * @var bool
   */
  protected $keepOrphans;

  /**
   * The migration ID.
   *
   * @var string
   */
  protected $migrationId;

  /**
   * Migration Config.
   *
   * @var array
   */
  protected $migrationConfig = [];

  /**
   * The original migration entity.
   *
   * @var \Drupal\customfeeds\Entity\MigrationInterface
   *   The migration entity object before configuration alterations.
   */
  protected $originalMigration;

  /**
   * The migration entity.
   *
   * @var \Drupal\customfeeds\Entity\MigrationInterface
   *   The migration entity object after configuration alterations.
   */
  protected $migration;

  /**
   * {@inheritdoc}
   */
  public function getImportFrequency() {
    return $this->importFrequency;
  }

  /**
   * {@inheritdoc}
   */
  public function setImportFrequency(int $importFrequency) {
    $this->importFrequency = $importFrequency;
  }

  /**
   * {@inheritdoc}
   */
  public function getExisting() {
    return $this->existing;
  }

  /**
   * {@inheritdoc}
   */
  public function setExisting(string $existing) {
    $this->existing = $existing;
  }

  /**
   * {@inheritdoc}
   */
  public function keepOrphans() {
    return $this->keepOrphans;
  }

  /**
   * {@inheritdoc}
   */
  public function setKeepOrphans(bool $keep_orphans) {
    $this->keepOrphans = $keep_orphans;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastRun() {
    return \Drupal::state()->get('feeds_migrate_importer.' . $this->id() . '.last_run', 0);
  }

  /**
   * {@inheritdoc}
   */
  public function setLastRun(int $last_run) {
    \Drupal::state()->set('feeds_migrate_importer.' . $this->id() . '.last_run', $last_run);
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCount() {
    return \Drupal::state()->get('feeds_migrate_importer.' . $this->id() . '.item_count', 0);
  }

  /**
   * {@inheritdoc}
   */
  public function setItemCount(int $item_count) {
    \Drupal::state()->set('feeds_migrate_importer.' . $this->id() . '.item_count', $item_count);
  }

  /**
   * {@inheritdoc}
   */
  public function getMigrationId() {
    return $this->migrationId;
  }

  /**
   * {@inheritdoc}
   */
  public function setMigrationId(string $id) {
    $this->migrationId = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalMigration() {
    if (!isset($this->originalMigration)) {
      $this->originalMigration = Migration::load($this->migrationId);
    }

    return $this->originalMigration;
  }

  /**
   * {@inheritdoc}
   */
  public function getMigration() {
    if (!isset($this->migration)) {
      /** @var \Drupal\customfeeds\Entity\MigrationInterface $altered_migration */
      $altered_migration = $this->migration = clone $this->getOriginalMigration();

      $source = array_merge($this->originalMigration->getSourceConfiguration(), $this->migrationConfig['source'] ?? []);
      $altered_migration->set('source', $source);
      $destination = array_merge($this->originalMigration->getDestinationConfiguration(), $this->migrationConfig['destination'] ?? []);
      $altered_migration->set('destination', $destination);
    }

    return $this->migration;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    if ($this->originalMigration) {
      // We should be able to use calculatePluginDependencies() here, but our
      // migration plugin doesn't have a provider, so it falls apart.
      $this->addDependency($this->originalMigration->getConfigDependencyKey(), $this->originalMigration->getConfigDependencyName());
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function needsImport() {
    $request_time = \Drupal::time()->getRequestTime();
    if ($this->importFrequency != FeedsMigrateImporterInterface::SCHEDULE_NEVER && ($this->getLastRun() + $this->importFrequency) <= $request_time) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getBatchExecutable() {
    $messenger = new MigrateMessage();
    return new FeedsMigrateBatchExecutable($this, $messenger);
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutable() {
    $messenger = new MigrateMessage();
    return new FeedsMigrateExecutable($this, $messenger);
  }

}
