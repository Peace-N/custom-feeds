<?php

namespace Drupal\customfeeds\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\customfeeds\Fetcher\CustomFeedsFetcher;

/** List Feed Items belonging to a Feed */
class FeedListItemController extends ControllerBase
{
  /**
   * Render a theme view for feeds
   * @return string[]
   */
  public function view()
  {
    return [
      "#theme" => "customfeeds_feed",
      "#content" => "Hello Template"
    ];
  }

  /**
   * List Feed Items
   * @return array
   */
  public function listItems()
  {
    $header = [
      'id' => $this->t('ID'),
      'title' => $this->t('Label'),
      'imported' => $this->t('Imported'),
      'guid' => [
        'data' => $this->t('GUID'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'url' => [
        'data' => $this->t('URL'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    $build = [];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => [],
      '#empty' => $this->t('There are no items yet.'),
    ];
    /** @var CustomFeedsFetcher $fetchResults */
    $fetchResults = \Drupal::service('customfeeds.fetcher');
    $customFeedsList = $fetchResults->fetch();
    header('Content-Type: application/xml');
    var_dump(simplexml_load_string($customFeedsList));
    return $build;
  }

}
