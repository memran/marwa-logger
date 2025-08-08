<?php

declare(strict_types=1);

namespace Marwa\Logging;

use Marwa\Logging\Config\Settings;
use Marwa\Logging\Contracts\LogFormatterInterface;
use Marwa\Logging\Contracts\SinkInterface;
use Marwa\Logging\Formatter\JsonFormatter;
use Marwa\Logging\Sink\FileSink;
use Marwa\Logging\Support\SensitiveDataFilter;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

/**
 * Central error/exception/shutdown handler.
 * - Dev: display + log all.
 * - Prod: never display; logs system-origin only; code keeps running.
 *
 * You can either:
 *   1) new ErrorHandler($options, ...) then ->register()
 *   2) ErrorHandler::bootstrap($options, ...) // builds & registers in one call
 */
final class ErrorHandler
{
    private bool $registered = false;

    public function __construct(
        /**
         * Options array for Settings::make(), e.g.:
         * [
         *   'app_name' => 'myapp',
         *   'env' => 'production',
         *   'log_path' => '/var/log/myapp',
         *   'max_log_bytes' => '10MB',
         *   'sensitive_keys' => ['password','token']
         * ]
         */
        private readonly array $options = [],

        /** Optional external deps (otherwise auto-wired) */
        private readonly ?CacheItemPoolInterface $cachePool = null,
        private readonly ?SinkInterface $sink = null,
        private readonly ?LogFormatterInterface $formatter = null,
        private readonly ?LoggerInterface $logger = null,
        private  ?ExceptionReporter $reporter = null,
    ) {}

    /**
     * One-call builder + register.
     */
    public static function bootstrap(
        array $options = [],
        ?CacheItemPoolInterface $cachePool = null,
        ?SinkInterface $sink = null,
        ?LogFormatterInterface $formatter = null,
        ?LoggerInterface $logger = null,
        ?ExceptionReporter $reporter = null
    ): self {
        $handler = new self(
            options: $options,
            cachePool: $cachePool,
            sink: $sink,
            formatter: $formatter,
            logger: $logger,
            reporter: $reporter
        );
        $handler->register();
        return $handler;
    }

    /**
     * Register PHP error/exception/shutdown hooks.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        // Wire dependencies (with fast no-op if already created)
        $settings  = Settings::make($this->options, $this->cachePool);
        $logger    = $this->logger ?? $this->buildLogger($settings);
        $reporter  = $this->reporter ?? new ExceptionReporter($settings, $logger);

        // PHP ini for dev/prod
        @ini_set('display_errors', $settings->displayErrors ? '1' : '0');
        @ini_set('log_errors', '0'); // we own logging
        error_reporting(E_ALL);

        set_error_handler(function (int $errno, string $errstr, ?string $file = null, ?int $line = null) use ($logger): bool {
            if (!(error_reporting() & $errno)) {
                return true; // respect @-silence
            }
            $logger->error('php_error', [
                '_origin' => 'system',
                'errno'   => $this->errnoName($errno),
                'message' => $errstr,
                'file'    => $file,
                'line'    => $line,
                '_trace'  => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            ]);
            return true; // swallow; continue execution
        });

        set_exception_handler(function (Throwable $e) use ($settings, $logger, $reporter): void {
            $handled = $reporter->report($e);

            if (!$handled) {
                $ctx = array_merge([
                    '_origin' => 'system',
                    'type'    => $e::class,
                    'code'    => (int)$e->getCode(),
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    '_trace'  => $e->getTrace(),
                ], $reporter->extraContext($e));

                $level = $reporter->level($e) ?? 'critical';
                $logger->log($level, 'uncaught_exception', $ctx);
            }

            if ($settings->displayErrors) {
                // Dev: bubble
                throw $e;
            }

            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
            }
            // Prod: show nothing (or render your generic error page)
        });

        register_shutdown_function(function () use ($logger): void {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $logger->alert('fatal_shutdown', [
                    '_origin' => 'system',
                    'errno'   => $this->errnoName($err['type']),
                    'message' => $err['message'] ?? null,
                    'file'    => $err['file'] ?? null,
                    'line'    => $err['line'] ?? null,
                ]);
            }
        });

        $logger->info('error_handler_booted', ['_origin' => 'system', 'php' => PHP_VERSION, 'sapi' => PHP_SAPI]);

        $this->registered = true;
    }

    /* ------------------------ Internals ------------------------ */

    private function buildLogger(Settings $settings): LoggerInterface
    {
        $sink = $this->sink ?? new FileSink(
            fs: new Filesystem(),
            basePath: $settings->logPath,
            filePrefix: $settings->appName,
            maxBytes: $settings->maxLogBytes
        );

        $formatter = $this->formatter ?? new JsonFormatter(); // JSON lines by default
        $filter    = new SensitiveDataFilter($settings->sensitiveKeys);

        /** @phpstan-ignore-next-line */
        return new Logger($settings, $sink, $formatter, $filter, channel: 'app');
    }

    private function errnoName(int $errno): string
    {
        return match ($errno) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'E_UNKNOWN',
        };
    }

    public function getLogger(): LoggerInterface
    {
        // In register() we create it, so ensure it's available
        if (!$this->registered) {
            $this->register();
        }
        return $this->logger ?? $this->buildLogger(Settings::make($this->options, $this->cachePool));
    }
    /**
     * Allow enabling or replacing the ExceptionReporter at runtime.
     */
    public function enableExceptionReporter(?ExceptionReporter $reporter = null): self
    {
        $settings = Settings::make($this->options, $this->cachePool);

        // If no reporter passed, build a default one
        $this->reporter = $reporter ?? new ExceptionReporter(
            $settings,
            $this->logger ?? $this->buildLogger($settings)
        );

        return $this;
    }

    /**
     * Get the current ExceptionReporter instance (if any).
     */
    public function getReporter(): ?ExceptionReporter
    {
        return $this->reporter;
    }
}
