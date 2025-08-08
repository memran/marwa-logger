<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Marwa\Logging\ErrorHandler;

$handler = ErrorHandler::bootstrap([
    'app_name'       => 'myapp',
    'env'            => 'development',         // or 'development'
    'log_path'       => __DIR__ . '/storage/logs',
    'max_log_bytes'  => '10MB',               // default 10MB if omitted/invalid
    'sensitive_keys' => ['password', 'token', 'authorization'],
    'display_errors' => false,                 // show errors in dev
]);
// That’s it. Handlers are registered.
$logger = $handler->getLogger();
$logger->warning('Manually triggered log', ['context' => 'test']);

// Dev: writes all user/system logs
$logger->info('button_clicked', ['user_id' => 123, 'page' => '/dashboard']);

// Prod: only writes if system-origin
$logger->error('cache_miss', ['_origin' => 'system', 'key' => 'user:123']);

// Simulate a PHP warning — will log, won’t break page in prod
strpos();
