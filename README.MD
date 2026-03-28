# memran/marwa-logger

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![PSR-3](https://img.shields.io/badge/PSR--3-compatible-0A7B83)
![CI](https://img.shields.io/badge/CI-GitHub%20Actions-2088FF?logo=githubactions&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan-2.x-231F20)
![PHPUnit](https://img.shields.io/badge/PHPUnit-10.5-3C873A)

A small PSR-3 logger for PHP 8.1+ that writes newline-delimited JSON, filters sensitive context data, and rotates files by size with optional retention cleanup.

## Features

- ✅ PSR-3 compatible `SimpleLogger`
- 🧾 NDJSON output for easy ingestion by log processors
- 🔒 Recursive sensitive-data filtering
- 🚦 Production gating by log level and request origin
- 🗂️ File rotation by size
- ♻️ Optional retention by age and file count
- 🧪 Composer scripts, PHPUnit, PHPStan, and CI included

## 📦 Installation

```bash
composer require memran/marwa-logger
```

## 🚀 Quick Start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Marwa\Logger\Logger;

$logger = Logger::boot(
    channel: 'payments',
    env: 'prod',
    path: __DIR__ . '/storage/logs',
    size: '10MB',
);

$logger->info('checkout_started', ['user_id' => 42]);
$logger->error('payment_failed', ['_origin' => 'system', 'order_id' => 991]);
```

`Logger::boot()` defaults to `APP_ENV` and `storage/logs` when `env` and `path` are omitted.

## ⚙️ Advanced File Sink Configuration

Use `StorageFactory` when you need retention controls:

```php
use Marwa\Logger\SimpleLogger;
use Marwa\Logger\Storage\StorageFactory;
use Marwa\Logger\Support\SensitiveDataFilter;
use Psr\Log\LogLevel;

$sink = StorageFactory::make([
    'driver' => 'file',
    'path' => __DIR__ . '/storage/logs',
    'prefix' => 'myapp',
    'max_bytes' => '10MB',
    'retention_days' => 7,
    'max_files' => 30,
]);

$logger = new SimpleLogger(
    appName: 'myapp',
    env: 'production',
    sink: $sink,
    filter: new SensitiveDataFilter(),
    logging: true,
    productionMinLevel: LogLevel::ERROR,
);
```

## 🧠 Runtime Behavior

In `production` or `prod`, user-origin logs are skipped unless `['_origin' => 'system']` is set. Levels below `productionMinLevel` are ignored. Sensitive keys such as `password`, `token`, `authorization`, and `client_secret` are redacted recursively.

Log records include timestamps, request metadata, PHP runtime details, message text, request IDs, and scrubbed context. Invalid UTF-8 is sanitized before encoding so log writes do not silently fail.

## 🗃️ File Rotation and Retention

Active files use the pattern `prefix-YYYY-MM-DD.log`. When `max_bytes` is exceeded, the current file is renamed to `prefix-YYYY-MM-DD_HHMMSS-<token>.log` and a fresh active file is created. If configured, `retention_days` deletes expired matching files and `max_files` keeps only the newest matching log files.

## 🚨 Error Handling

The file sink throws `RuntimeException` when it cannot create the log directory, rotate a file, or append a record. Treat these as operational failures and surface them in deployment monitoring.

## 🛠️ Development

```bash
composer lint
composer analyze
composer test
composer check
```

`composer check` runs syntax checks, PHPStan 2.x, and PHPUnit.
