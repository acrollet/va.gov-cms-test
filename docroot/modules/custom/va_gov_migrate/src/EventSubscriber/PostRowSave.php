<?php

namespace Drupal\va_gov_migrate\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\va_gov_migrate\ParagraphMigrator;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\node\Entity\Node;

/**
 * Add paragraphs to node after save.
 *
 * @package Drupal\va_gov_migrate\EventSubscriber
 */
class PostRowSave implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE] = 'onMigratePostRowSave';
    return $events;
  }

  /**
   * Perform Post Row Save events.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   Information about the event that triggered this function.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\migrate\MigrateException
   */
  public function onMigratePostRowSave(MigratePostRowSaveEvent $event) {
    $migrator = new ParagraphMigrator($event);

    switch ($event->getMigration()->id()) {
      case 'va_healthcare':
        $this->convertIntroTextToPlainText($event->getDestinationIdValues()[0]);
        $migrator->process('related_links', 'field_related_links');
        $migrator->process('featured_content', 'field_featured_content');
        $migrator->process(['body', 'nav_linkslist'], 'field_content_block');
        $this->setChangedDate($event);
        break;

      case 'va_alert_block':
        $migrator->process('alert_body', 'field_alert_content');
        break;

      case 'va_hub':
        $this->convertIntroTextToPlainText($event->getDestinationIdValues()[0]);
        $migrator->process('related_links', 'field_related_links');
        $migrator->process('hub_links', 'field_spokes');
        break;

      case 'va_promo':
        $migrator->process('body', 'field_promo_link');
        break;
    }
  }

  /**
   * Turns intro text content into plain text.
   *
   * Should be run on any migration that includes the Intro Text field.
   *
   * @todo Do this during the actual migration instead of on post row save.
   *
   * @param int $nid
   *   The nid of the node to work on.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function convertIntroTextToPlainText($nid) {
    $node = Node::load($nid);
    $intro_text = $node->get('field_intro_text')->value;
    $intro_text = preg_replace('/<\/p>\s+<p>/', PHP_EOL . PHP_EOL, $intro_text);
    $intro_text = strip_tags($intro_text);
    $node->set('field_intro_text', $intro_text);
    $node->save();

  }

  /**
   * Set the changed (Updated) date for a node.
   *
   * This should be done after other post row save events so that they don't
   * update the changed date after it's been set.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The event record for the node.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setChangedDate(MigratePostRowSaveEvent $event) {
    $node = Node::load($event->getDestinationIdValues()[0]);
    // The timestamp we receive is 4 hours behind when displayed by drupal.
    // So add 4 hours to account for that.
    $last_update = $event->getRow()->getSourceProperty('lastupdate') + 3600 * 4;
    $node->set('changed', $last_update);
    $node->save();
  }

}