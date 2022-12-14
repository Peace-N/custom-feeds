<?php

namespace Drupal\customfeeds;

use Drupal;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigrateMapDeleteEvent;
use Drupal\migrate\Event\MigrateMapSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Event\MigrateEvents as MigratePlusEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;

/**
 * Defines a migrate executable class for drush.
 */
class FeedsMigrateExecutable extends MigrateExecutable {

  /**
   * Plugin manager for migration plugins.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * The configuration of the feeds migrate importer.
   *
   * @var \Drupal\customfeeds\FeedsMigrateImporterInterface
   */
  protected $importer;

  /**
   * Counters of map statuses.
   *
   * @var array
   *   Set of counters, keyed by MigrateIdMapInterface::STATUS_* constant.
   */
  protected $saveCounters = [
    MigrateIdMapInterface::STATUS_FAILED => 0,
    MigrateIdMapInterface::STATUS_IGNORED => 0,
    MigrateIdMapInterface::STATUS_IMPORTED => 0,
    MigrateIdMapInterface::STATUS_NEEDS_UPDATE => 0,
  ];

  /**
   * Counter of map deletions.
   *
   * @var int
   */
  protected $deleteCounter = 0;

  /**
   * Maximum number of items to process in this migration.
   *
   * 0 indicates no limit is to be applied.
   *
   * @var int
   */
  protected $itemLimit = 0;

  /**
   * Frequency (in items) at which progress messages should be emitted.
   *
   * @var int
   */
  protected $feedback = 0;

  /**
   * List of specific source IDs to import.
   *
   * @var array
   */
  protected $idlist = [];

  /**
   * Count of number of items processed so far in this migration.
   *
   * @var int
   */
  protected $counter = 0;

  /**
   * Whether the destination item exists before saving.
   *
   * @var bool
   */
  protected $preExistingItem = FALSE;

  /**
   * List of event listeners we have registered.
   *
   * @var array
   */
  protected $listeners = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(FeedsMigrateImporterInterface $importer, MigrateMessageInterface $message, array $options = []) {
    $this->importer = $importer;
    /* @var \Drupal\migrate\Plugin\MigrationPluginManager $migration_manager */
    $this->migrationPluginManager = Drupal::service('plugin.manager.migration');
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration_plugin */
    $migration_plugin = $this->migrationPluginManager->createInstance($importer->getMigrationId(), $importer->getMigration()->toArray());

    parent::__construct($migration_plugin, $message);

    if ($importer->getExisting() == FeedsMigrateImporterInterface::EXISTING_UPDATE) {
      $migration_plugin->getIdMap()->prepareUpdate();
    }

    if (isset($options['limit'])) {
      $this->itemLimit = $options['limit'];
    }
    if (isset($options['feedback'])) {
      $this->feedback = $options['feedback'];
    }
    if (isset($options['idlist'])) {
      if (is_string($options['idlist'])) {
        $this->idlist = explode(',', $options['idlist']);
        array_walk($this->idlist, function (&$value, $key) {
          $value = explode(':', $value);
        });
      }
    }

    $this->listeners[MigrateEvents::MAP_SAVE] = [$this, 'onMapSave'];
    $this->listeners[MigrateEvents::MAP_DELETE] = [$this, 'onMapDelete'];
    $this->listeners[MigrateEvents::POST_IMPORT] = [$this, 'onPostImport'];
    $this->listeners[MigrateEvents::POST_ROLLBACK] = [$this, 'onPostRollback'];
    $this->listeners[MigrateEvents::PRE_ROW_SAVE] = [$this, 'onPreRowSave'];
    $this->listeners[MigrateEvents::POST_ROW_DELETE] = [
      $this,
      'onPostRowDelete',
    ];
    $this->listeners[MigratePlusEvents::PREPARE_ROW] = [$this, 'onPrepareRow'];
    foreach ($this->listeners as $event => $listener) {
      \Drupal::service('event_dispatcher')->addListener($event, $listener);
    }
  }

  /**
   * Count up any map save events.
   *
   * @param \Drupal\migrate\Event\MigrateMapSaveEvent $event
   *   The map event.
   */
  public function onMapSave(MigrateMapSaveEvent $event) {
    // Only count saves for this migration.
    if ($event->getMap()
        ->getQualifiedMapTableName() == $this->migration->getIdMap()
        ->getQualifiedMapTableName()) {
      $fields = $event->getFields();
      // Distinguish between creation and update.
      if ($fields['source_row_status'] == MigrateIdMapInterface::STATUS_IMPORTED &&
        $this->preExistingItem
      ) {
        $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE]++;
      }
      else {
        $this->saveCounters[$fields['source_row_status']]++;
      }
    }
  }

  /**
   * Count up any rollback events.
   *
   * @param \Drupal\migrate\Event\MigrateMapDeleteEvent $event
   *   The map event.
   */
  public function onMapDelete(MigrateMapDeleteEvent $event) {
    $this->deleteCounter++;
  }

  /**
   * Return the number of items created.
   *
   * @return int
   *   The number of items created.
   */
  public function getCreatedCount() {
    return $this->saveCounters[MigrateIdMapInterface::STATUS_IMPORTED];
  }

  /**
   * Return the number of items updated.
   *
   * @return int
   *   The updated count.
   */
  public function getUpdatedCount() {
    return $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE];
  }

  /**
   * Return the number of items ignored.
   *
   * @return int
   *   The ignored count.
   */
  public function getIgnoredCount() {
    return $this->saveCounters[MigrateIdMapInterface::STATUS_IGNORED];
  }

  /**
   * Return the number of items that failed.
   *
   * @return int
   *   The failed count.
   */
  public function getFailedCount() {
    return $this->saveCounters[MigrateIdMapInterface::STATUS_FAILED];
  }

  /**
   * Return the total number of items processed.
   *
   * Note that STATUS_NEEDS_UPDATE is not counted, since this is typically set
   * on stubs created as side effects, not on the primary item being imported.
   *
   * @return int
   *   The processed count.
   */
  public function getProcessedCount() {
    return $this->saveCounters[MigrateIdMapInterface::STATUS_IMPORTED] +
      $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE] +
      $this->saveCounters[MigrateIdMapInterface::STATUS_IGNORED] +
      $this->saveCounters[MigrateIdMapInterface::STATUS_FAILED];
  }

  /**
   * Return the number of items rolled back.
   *
   * @return int
   *   The rollback count.
   */
  public function getRollbackCount() {
    return $this->deleteCounter;
  }

  /**
   * Reset all the per-status counters to 0.
   */
  protected function resetCounters() {
    foreach ($this->saveCounters as $status => $count) {
      $this->saveCounters[$status] = 0;
    }
    $this->deleteCounter = 0;
  }

  /**
   * React to migration completion.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The map event.
   */
  public function onPostImport(MigrateImportEvent $event) {
    $migrate_last_imported_store = \Drupal::keyValue('migrate_last_imported');
    $migrate_last_imported_store->set($event->getMigration()
      ->id(), round(microtime(TRUE) * 1000));
    $this->importer->setItemCount($this->getProcessedCount());
    $this->progressMessage();
    $this->removeListeners();
  }

  /**
   * Clean up all our event listeners.
   */
  protected function removeListeners() {
    foreach ($this->listeners as $event => $listener) {
      \Drupal::service('event_dispatcher')->removeListener($event, $listener);
    }
  }

  /**
   * Emit information on what we've done.
   *
   * Either since the last feedback or the beginning of this migration.
   *
   * @param bool $done
   *   TRUE if this is the last items to process. Otherwise FALSE.
   */
  protected function progressMessage($done = TRUE) {
    $processed = $this->getProcessedCount();
    if ($done) {
      $singular_message = "Processed 1 item (@created created, @updated updated, @failures failed, @ignored ignored) - done with '@name'";
      $plural_message = "Processed @numitems items (@created created, @updated updated, @failures failed, @ignored ignored) - done with '@name'";
    }
    else {
      $singular_message = "Processed 1 item (@created created, @updated updated, @failures failed, @ignored ignored) - continuing with '@name'";
      $plural_message = "Processed @numitems items (@created created, @updated updated, @failures failed, @ignored ignored) - continuing with '@name'";
    }
    $this->message->display(\Drupal::translation()->formatPlural($processed,
      $singular_message, $plural_message,
      [
        '@numitems' => $processed,
        '@created' => $this->getCreatedCount(),
        '@updated' => $this->getUpdatedCount(),
        '@failures' => $this->getFailedCount(),
        '@ignored' => $this->getIgnoredCount(),
        '@name' => $this->migration->id(),
      ]
    ));
  }

  /**
   * React to rollback completion.
   *
   * @param \Drupal\migrate\Event\MigrateRollbackEvent $event
   *   The map event.
   */
  public function onPostRollback(MigrateRollbackEvent $event) {
    $this->rollbackMessage();
    $this->removeListeners();
  }

  /**
   * Emit information on what we've done.
   *
   * Either since the last feedback or the beginning of this migration.
   *
   * @param bool $done
   *   TRUE if this is the last items to rollback. Otherwise FALSE.
   */
  protected function rollbackMessage($done = TRUE) {
    $rolled_back = $this->getRollbackCount();
    if ($done) {
      $singular_message = "Rolled back 1 item - done with '@name'";
      $plural_message = "Rolled back @numitems items - done with '@name'";
    }
    else {
      $singular_message = "Rolled back 1 item - continuing with '@name'";
      $plural_message = "Rolled back @numitems items - continuing with '@name'";
    }
    $this->message->display(\Drupal::translation()->formatPlural($rolled_back,
      $singular_message, $plural_message,
      [
        '@numitems' => $rolled_back,
        '@name' => $this->migration->id(),
      ]
    ));
  }

  /**
   * React to an item about to be imported.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   The pre-save event.
   */
  public function onPreRowSave(MigratePreRowSaveEvent $event) {
    $id_map = $event->getRow()->getIdMap();
    if (!empty($id_map['destid1'])) {
      $this->preExistingItem = TRUE;
    }
    else {
      $this->preExistingItem = FALSE;
    }
  }

  /**
   * React to item rollback.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   The post-save event.
   */
  public function onPostRowDelete(MigrateRowDeleteEvent $event) {
    if ($this->feedback && ($this->deleteCounter) && $this->deleteCounter % $this->feedback == 0) {
      $this->rollbackMessage(FALSE);
      $this->resetCounters();
    }
  }

  /**
   * React to a new row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare-row event.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    if (!empty($this->idlist)) {
      $row = $event->getRow();
      // TODO: replace for $source_id = $row->getSourceIdValues();
      // when https://www.drupal.org/node/2698023 is fixed.
      $migration = $event->getMigration();
      $source_id = array_merge(array_flip(array_keys($migration->getSourcePlugin()
        ->getIds())), $row->getSourceIdValues());
      $skip = TRUE;
      foreach ($this->idlist as $item) {
        if (array_values($source_id) == $item) {
          $skip = FALSE;
          break;
        }
      }
      if ($skip) {
        throw new MigrateSkipRowException(NULL, FALSE);
      }
    }
    if ($this->feedback && ($this->counter) && $this->counter % $this->feedback == 0) {
      $this->progressMessage(FALSE);
      $this->resetCounters();
    }
    $this->counter++;
    if ($this->itemLimit && ($this->getProcessedCount() + 1) >= $this->itemLimit) {
      $event->getMigration()
        ->interruptMigration(MigrationInterface::RESULT_COMPLETED);
    }

  }

  /**
   * Import a single row.
   *
   * @param \Drupal\migrate\Row $row
   *   The row to be processed.
   */
  public function importRow(Row $row) {
    $id_map = $this->migration->getIdMap();
    $destination = $this->migration->getDestinationPlugin();
    $this->sourceIdValues = $row->getSourceIdValues();

    try {
      $this->processRow($row);
      $save = TRUE;
    }
    catch (MigrateException $e) {
      $this->migration->getIdMap()->saveIdMapping($row, [], $e->getStatus());
      $this->saveMessage($e->getMessage(), $e->getLevel());
      $save = FALSE;
    }
    catch (MigrateSkipRowException $e) {
      if ($e->getSaveToMap()) {
        $id_map->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_IGNORED);
      }
      if ($message = trim($e->getMessage())) {
        $this->saveMessage($message, MigrationInterface::MESSAGE_INFORMATIONAL);
      }
      $save = FALSE;
    }

    if ($save) {
      try {
        $this->getEventDispatcher()
          ->dispatch(MigrateEvents::PRE_ROW_SAVE, new MigratePreRowSaveEvent($this->migration, $this->message, $row));
        $destination_ids = $id_map->lookupDestinationIds($this->sourceIdValues);
        $destination_id_values = $destination_ids ? reset($destination_ids) : [];
        $destination_id_values = $destination->import($row, $destination_id_values);
        $this->getEventDispatcher()
          ->dispatch(MigrateEvents::POST_ROW_SAVE, new MigratePostRowSaveEvent($this->migration, $this->message, $row, $destination_id_values));
        if ($destination_id_values) {
          // We do not save an idMap entry for config.
          if ($destination_id_values !== TRUE) {
            $id_map->saveIdMapping($row, $destination_id_values, MigrateIdMapInterface::STATUS_IMPORTED, $destination->rollbackAction());
          }
        }
        else {
          $id_map->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_FAILED);
          if (!$id_map->messageCount()) {
            $message = $this->t('New object was not saved, no error provided');
            $this->saveMessage($message);
            $this->message->display($message);
          }
        }
      }
      catch (MigrateException $e) {
        $this->migration->getIdMap()
          ->saveIdMapping($row, [], $e->getStatus());
        $this->saveMessage($e->getMessage(), $e->getLevel());
      }
      catch (\Exception $e) {
        $this->migration->getIdMap()
          ->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_FAILED);
        $this->handleException($e);
      }
    }
  }

  /**
   * Retrieve the migration.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface
   *   The migration to execute.
   */
  public function getMigration() {
    return $this->migration;
  }

}
