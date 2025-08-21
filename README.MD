# memran/marwa-logger

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

$filter = new SensitiveDataFilter(['password','token','authorization']);
$sink   = StorageFactory::make([
  'driver'    => 'file',
  'path'      => __DIR__.'/storage/logs',
  'prefix'    => 'myapp',
  'max_bytes' => '10MB',
]);

$logger = new SimpleLogger(
  appName: 'myapp',
  env: 'production',
  sink: $sink,
  filter: $filter,
  logging: true               // true in development
);

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