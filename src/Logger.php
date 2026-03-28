<?php

declare(strict_types=1);

namespace Marwa\Logger;

use Marwa\Logger\SimpleLogger;
use Marwa\Logger\Support\SensitiveDataFilter;
use Marwa\Logger\Storage\StorageFactory;
use Psr\Log\LogLevel;

/**
 * Logger Bootstrapper
 *
 * Provides environment-aware bootstrapping for the Logger instance.
 */
final class Logger
{
    /**
     * Boot and create a Logger instance
     *
     * @param string|null $channel
     * @param string|null $env
     * @return \Marwa\Logger\SimpleLogger
     */
    public static function boot(
        ?string $channel = 'app',
        ?string $env = null,
        ?string $path = null,
        string $size = "10MB"
    ): SimpleLogger {
        // Detect environment (default: dev)
        $env = $env ?? getenv('APP_ENV') ?: 'dev';
        $channel = $channel ?: 'app';
        $path = $path ?: (getcwd() . '/storage/logs');

        $filter = new SensitiveDataFilter();
        $sink   = StorageFactory::make([
            'driver'    => 'file',
            'path'      => $path,
            'prefix'    => $channel,
            'max_bytes' => $size,
        ]);
        return new SimpleLogger(
            appName: $channel,
            env: $env,
            sink: $sink,
            filter: $filter,
            logging: true,
            productionMinLevel: LogLevel::ERROR    // only ERROR and above
        );
    }

    /**
     * Alias of boot() for those who prefer init() naming.
     */
    public static function init(
        ?string $channel = 'app',
        ?string $env = null,
    ): SimpleLogger {
        return self::boot($channel, $env);
    }
}
