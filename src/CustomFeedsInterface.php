<?php
namespace Drupal\customfeeds;

/**
 * Interface CustomFeedsInterface
 * @package Drupal\customfeeds
 */
interface CustomFeedsInterface
{
  const ADMIN_SETTINGS_FORM_ID = '_custom_feeds_settings';
  const ADMIN_SETTINGS_RSS_ATOM_FEED_NAME = 'rss_atom_feed_name';
  const ADMIN_SETTINGS_RSS_ATOM_FEED_URL = 'rss_atom_feed_url';
  const ADMIN_SETTINGS_RSS_ATOM_FEED_SCHEDULE = 'rss_atom_feed_import_schedule';
  const CUSTOM_FEEDS_CONFIG_VALUES = 'custom_feeds_settings_values';
}
