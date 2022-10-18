## custom-feeds

This is a Drupal Custom feeds module for fetching feeds from multiple sources. Included in the module is routing and service modules.
This Module also has Multiple Dependencies setup. Installation requires multiple dependencies to be setup as well.

## Dependencies##
###composer update###
"drupal/migrate_plus": "^4.0|^5.0",
"drupal/migrate_tools": "^4.0|^5.0"
"bex/behat-screenshot": ">=1.2",
"composer/installers": ">=1.2",
"cweagans/composer-patches": ">=1.6",
"drupal/coder": ">=8.3",
"phpmd/phpmd": ">=2.6",
"phpmetrics/phpmetrics": ">=2.4",
"squizlabs/php_codesniffer": ">=3.0.1",
"symfony/phpunit-bridge": ">=3.4.3",
"symfony/psr-http-message-bridge": "^1.1.1 | ^2.0"
## Modules ##
- A UI that extends the Drupal Migration UI, uses Feeds Migration UI
- Clearley defined Parsers for XML and JSON
## problem-statement-solving
Our suggested way on reimporting feeds missing images is to create entries of failed imports and run a cron to reprocess feeds with missing images.

## Entities
Entities are important in Mapping imported feeds, content reuse and continoues import on feed items (nodes)

## monitoring

Monitoring would have to be through exception logging which on further development, we could set up monitoring services like sentry.io
Event Distribution and setup of queue workers and setting up Publishing and Subcribing of events in the module for module self updates.

## Easisest way to test the module
Testing can be setup using a CI tool like Circle CI and Automated Browser Testing .
2. go to admin/config/development/testing
3. select test 'custom feeds'
4. press 'Run tests'

## Management URLS
--- imported feeds screen
/admin/content/customfeeds

--- configuration screen
/admin/config/customfeeds-settings

### Still to do ###
to ignore /*vendors on gitignore
tie up event distribution pubsub via a cache relay service.
