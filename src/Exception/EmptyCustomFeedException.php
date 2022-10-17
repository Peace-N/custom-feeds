<?php

namespace Drupal\customfeeds\Exception;

use Drupal\feeds\Exception\FeedsRuntimeException;

/**
 * Thrown if a feed is empty to abort importing.
 */
class EmptyCustomFeedException extends FeedsRuntimeException {}
