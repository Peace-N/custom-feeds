<?php

namespace Drupal\customfeeds;

interface CustomFeedsFetchInterface
{
 public function fetch();
 public function build(string $url);
}
