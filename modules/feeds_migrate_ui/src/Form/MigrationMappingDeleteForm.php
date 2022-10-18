<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a confirmation form for deleting a single mapping row.
 *
 * @package Drupal\feeds_migrate_ui\Form
 */
class MigrationMappingDeleteForm extends EntityConfirmFormBase {

  /**
   * Manager for entity fields.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * The destination key.
   *
   * This is filled out when we are not migrating into a standard Drupal field
   * instance (e.g. table column name, virtual field etc...)
   *
   * @var string
   */
  protected $destinationKey;

  /**
   * Field mapping for this migration.
   *
   * @var array
   */
  protected $mapping = [];

  /**
   * Constructs a new MigrationMappingDeleteForm object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   *   Field manager service.
   */
  public function __construct(EntityFieldManager $field_manager) {
    $this->fieldManager = $field_manager;
  }

  /**
   * Sets the destination key.
   *
   * @param string $key
   *   The destination key.
   */
  public function setDestinationKey($key) {
    $this->destinationKey = $key;
  }

  /**
   * Gets the label for the destination field - if any.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The label of the field, or key if custom property.
   */
  public function getDestinationFieldLabel() {
    $field = $this->entity->getDestinationField($this->destinationKey);
    if ($field) {
      return $field->getLabel();
    }
    return $this->destinationKey;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the mapping for %destination_field for migration %migration?', [
      '%destination_field' => $this->getDestinationFieldLabel(),
      '%migration' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url("entity.migration.mapping.list", [
      'migration' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!isset($this->destinationKey)) {
      throw new NotFoundHttpException();
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\feeds_migrate\Entity\MigrationInterface $migration */
    $migration = $this->entity;
    // Remove the mapping from the migration process array.
    $migration->removeMapping($this->destinationKey)
      ->save();

    $this->messenger()->addMessage($this->t('Mapping for %destination_field deleted.', [
      '%destination_field' => $this->getDestinationFieldLabel(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
