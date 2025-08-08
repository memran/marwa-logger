# memran/marwa-logger

A **PSR-3 compliant**, Laravel-style, dynamic, and configurable logging library for PHP.  
It automatically handles error & exception logging behind the scenes, with environment-based behavior for development and production.

---

## ✨ Features

- **PSR-3 Compliant** — Works with any PSR-3 logger consumers.
- **Laravel-style Exception Reporting** — `dontReport`, `reportable`, and `fallback` handlers.
- **Dynamic Storage** — File, Database (PSR-6 Cache/DB), Kafka (future extension).
- **Sensitive Data Filtering** — Automatically masks keys like `password`, `token`.
- **Environment Auto-config** — Dev mode logs all; Prod mode logs only system-origin events.
- **Custom JSON Log Format** — Developer-friendly and machine-parseable.
- **Detailed Context Capture** — Request data, environment, trace, and more.
- **Log Rotation** — Date-based filenames, size-based rollover.
- **Symfony Filesystem Support** — Safe, atomic writes.
- **Future-Ready** — Easy to extend to Kafka streams for real-time debugging.

---

## 📦 Installation

```bash
composer require memran/marwa-logger
```

---

## 🚀 Quick Start

```php
use Marwa\Logging\ErrorHandler;

$handler = ErrorHandler::bootstrap([
    'app_name'       => 'myapp',
    'env'            => 'development',   // or 'production'
    'log_path'       => __DIR__ . '/storage/logs',
    'max_log_bytes'  => '10MB',
    'sensitive_keys' => ['password', 'token', 'authorization'],
]);

// Enable Laravel-style reporter
$handler->enableExceptionReporter();

// Get the logger (manual logging)
$logger = $handler->getLogger();
$logger->info('User action', ['user_id' => 123]);
```

---

## 📜 Manual Logging

Since the logger is PSR-3 compliant, you can use:

```php
$logger->emergency($message, $context);
$logger->alert($message, $context);
$logger->critical($message, $context);
$logger->error($message, $context);
$logger->warning($message, $context);
$logger->notice($message, $context);
$logger->info($message, $context);
$logger->debug($message, $context);
```

---

## 🛠 Laravel-Style Exception Reporting

```php
$reporter = $handler->getReporter();

// Ignore specific exceptions
$reporter->dontReport([\InvalidArgumentException::class]);

// Custom handling for specific types
$reporter->reportable(\RuntimeException::class, function ($e, $logger) {
    $logger->critical('Runtime failure', ['message' => $e->getMessage()]);
});

// Fallback handler
$reporter->fallback(function ($e, $logger) {
    $logger->alert('Unhandled exception', ['type' => get_class($e)]);
});
```

---

## ⚙ Settings

You can pass an array of options to `ErrorHandler::bootstrap()`:

| Key              | Type     | Default        | Description                       |
| ---------------- | -------- | -------------- | --------------------------------- |
| `app_name`       | string   | `app`          | Application name prefix for logs  |
| `env`            | string   | `production`   | `development` or `production`     |
| `log_path`       | string   | `storage/logs` | Directory for log files           |
| `max_log_bytes`  | string   | `5MB`          | Max log file size before rotation |
| `sensitive_keys` | string[] | `[]`           | Keys to mask in logs              |

---

## 📄 License

This library is open-sourced software licensed under the [MIT license](LICENSE).

---

## 📬 Future Plans

- Kafka integration for real-time log streaming
- Web panel log viewer with filtering/search
- Database + cache log storage
