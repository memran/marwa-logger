# memran/marwa-logger

[![Latest Version](https://img.shields.io/packagist/v/memran/marwa-logger.svg?style=flat-square)](https://packagist.org/packages/memran/marwa-logger)
[![Total Downloads](https://img.shields.io/packagist/dt/memran/marwa-logger.svg?style=flat-square)](https://packagist.org/packages/memran/marwa-logger)
[![License](https://img.shields.io/packagist/l/memran/marwa-logger.svg?style=flat-square)](https://packagist.org/packages/memran/marwa-logger)
[![PHP Version](https://img.shields.io/packagist/php-v/memran/marwa-logger.svg?style=flat-square)](https://php.net)

A tiny, fast **PSR-3** logger that writes **JSON lines (NDJSON)**, with:

- ✅ **Sensitive-data filtering** (recursive, configurable keys)
- ✅ **Pluggable storage** via `SinkInterface` (File sink now; Kafka/DB-ready)
- ✅ **Date + size rotation** (e.g. `myapp-YYYY-MM-DD.log`, rolls when `max_bytes` hit)
- ✅ **Dev vs Prod** behavior (prod ignores “user-origin” logs unless `['_origin'=>'system']`)

## Install

```bash
composer require memran/marwa-logger
```

# Quick Example

```bash
use Marwa\Logger\SimpleLogger;
use Marwa\Logger\Support\SensitiveDataFilter;
use Marwa\Logger\Storage\StorageFactory;
use Psr\Log\LogLevel;

$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$sink = StorageFactory::make([
    'driver'    => 'file',
    'path'      => $logDir,
    'prefix'    => 'myapp',
    'max_bytes' => '10MB',
]);

$logger = new SimpleLogger(
    appName: 'myapp',
    env: 'production',
    sink: $sink,
    filter: new SensitiveDataFilter(),
    logging: true,                         // turn on logging
    productionMinLevel: LogLevel::ERROR    // only ERROR and above
);

// ignored: user-origin in prod
$logger->info('user clicked', ['_origin' => 'user']);

// ignored: system but too low (warning < error)
$logger->warning('slow_query', ['_origin' => 'system']);

// logged: system + error
$logger->error('db_connect_failed', ['_origin' => 'system', 'dsn' => 'mysql:...']);

// Prod ignores user-origin logs:
$logger->info('clicked', ['user_id'=>1]);                         // ignored (user-origin)
$logger->error('cache_miss', ['_origin'=>'system','key'=>'user']); // logged
```

# Example Usage

```bash
<?php

require __DIR__ . '/../vendor/autoload.php';

use Marwa\Logger\Logger;

// Boot logger (env auto-detected from APP_ENV or defaults to dev)
$logger = Logger::boot('my-app');

// Write logs
$logger->info("Application started", ['env' => getenv('APP_ENV')]);
$logger->error("Critical failure", ['exception' => 'RuntimeException']);

// Or use init() alias
$logger = Logger::init('payments', 'prod');
$logger->warning("Payment delayed", ['user_id' => 42]);

```
