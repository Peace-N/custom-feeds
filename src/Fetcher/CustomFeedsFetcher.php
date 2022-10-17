<?php

namespace Drupal\customfeeds\Fetcher;

use Drupal\customfeeds\CustomFeedsFetchInterface;
use Drupal\customfeeds\CustomFeedsInterface;
use Drupal\customfeeds\Exception\EmptyCustomFeedException;
use Drupal\feeds\Utility\Feed;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Response;

/**
 * AN Extension of Drupal Core Fetcher for feeds Class
 *
 * @FeedsFetcher(
 *   id = "custom_feeds_fetcher",
 *   title = @Translation("Custom Feeds Fetcher"),
 *   description = @Translation("Custom Feeds Fetcher"),
 * )
 */
class CustomFeedsFetcher implements CustomFeedsFetchInterface
{

  /**
   * The Guzzle client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $client;

  /**
   * @var array|string[]
   */
  protected array $options;

  /**
   * @var mixed|string
   */
  protected string $url;

  public function __construct(ClientInterface $client)
  {
    $collection = \Drupal::state()->get(CustomFeedsInterface::CUSTOM_FEEDS_CONFIG_VALUES);
    $this->url = $collection[CustomFeedsInterface::ADMIN_SETTINGS_RSS_ATOM_FEED_URL] ?: "";
    $scheduleCalls = $collection[CustomFeedsInterface::ADMIN_SETTINGS_RSS_ATOM_FEED_SCHEDULE] ?: "";
    $options = [RequestOptions::SINK => "sink"];
    $this->options = $options;
    $this->client = $client;
    if (isset($this->client->getConfig('headers')['User-Agent'])) {
      $options[RequestOptions::HEADERS]['User-Agent'] = $this->client->getConfig('headers')['User-Agent'];
    }
  }

  /**
   * Fetch Feed from a URL in config values
   * @return mixed
   */
  public function fetch()
  {
    $response = $this->build($this->url);
    // 304, nothing to see here.
    if ($response->getStatusCode() == Response::HTTP_NOT_MODIFIED) {
      throw new EmptyCustomFeedException();
    }
    $data = $response->getBody()->getContents();
    return $data;
  }

  /**
   * Build the query as per the contract with the interface
   * @param string $url
   * @return mixed
   */
  public function build($url)
  {
    $url = Feed::translateSchemes($url);
    try {
      $response = $this->client->getAsync($url, $this->options)->wait();
    } catch (RequestException $e) {
      $args = ['%site' => $this->url, '%error' => $e->getMessage()];
      throw new \RuntimeException('The feed from %site seems to be broken because of error "%error".', $args);
    }
    return $response;
  }
}
