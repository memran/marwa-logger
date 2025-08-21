<?php declare(strict_types=1);
/**
 * Example usage of the Marwa Logger package.
 * This script demonstrates how to initialize the logger and log messages.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Marwa\Logger\SimpleLogger;
use Marwa\Logger\Support\SensitiveDataFilter;
use Marwa\Logger\Storage\StorageFactory;

$filter = new SensitiveDataFilter(['password','token','authorization']);
$sink   = StorageFactory::make([
  'driver'    => 'file',
  'path'      => __DIR__.'/storage/logs',
  'prefix'    => 'myapp',
  'max_bytes' => '10MB',
]);

$logger = new SimpleLogger(
  appName: 'myapp',
  env: 'development', // or 'production', 'testing
  sink: $sink,
  filter: $filter,
  logging: true // Enable logging
);

// Prod ignores user-origin logs:
$logger->info('clicked', ['user_id'=>1]);                         // ignored (user-origin)
$logger->error('cache_miss', ['_origin'=>'system','key'=>'user']); // logged
$logger->warning('This is a warning message'); // logged
$logger->debug('This is a debug message');   // ignored in production

die('Logging initialized. Check the logs in ');
