<?php

declare(strict_types=1);

namespace Marwa\Logging;

use Marwa\Logging\Config\Settings;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Central exception reporter for manual and automatic exception logging.
 *
 * Features:
 * - `dontReport()` list to skip certain exception classes
 * - `reportable()` callbacks per exception type
 * - Automatic default logging if no custom handler
 * - Fallback handler for anything unhandled
 */
final class ExceptionReporter
{
    /** @var array<class-string<Throwable>> */
    private array $dontReport = [];

    /** @var array<class-string<Throwable>, callable(Throwable, LoggerInterface):void> */
    private array $customReporters = [];

    /** @var callable(Throwable, LoggerInterface):void|null */
    private $fallbackReporter = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Add exception classes to ignore from logging.
     *
     * @param array<class-string<Throwable>> $classes
     */
    public function dontReport(array $classes): void
    {
        $this->dontReport = array_merge($this->dontReport, $classes);
    }

    /**
     * Register a custom reporter for a specific exception type.
     *
     * @template T of Throwable
     * @param class-string<T> $class
     * @param callable(T, LoggerInterface):void $callback
     */
    public function reportable(string $class, callable $callback): void
    {
        $this->customReporters[$class] = $callback;
    }

    /**
     * Set a fallback reporter if no match found.
     *
     * @param callable(Throwable, LoggerInterface):void $callback
     */
    public function fallback(callable $callback): void
    {
        $this->fallbackReporter = $callback;
    }

    /**
     * Report an exception.
     */
    public function report(Throwable $e): bool
    {
        // Ignore if in dontReport list
        foreach ($this->dontReport as $ignore) {
            if ($e instanceof $ignore) {
                return true;
            }
        }

        // Run custom reporter if registered
        foreach ($this->customReporters as $type => $callback) {
            if ($e instanceof $type) {
                $callback($e, $this->logger);
                return true;
            }
        }

        // Default reporting
        $this->logger->error('uncaught_exception', [
            '_origin' => 'system',
            'type'    => $e::class,
            'code'    => (int)$e->getCode(),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            '_trace'  => $e->getTrace(),
        ]);

        // Run fallback if set
        if ($this->fallbackReporter) {
            ($this->fallbackReporter)($e, $this->logger);
        }

        return false;
    }

    /**
     * Extra context hook — you can extend this later.
     */
    public function extraContext(Throwable $e): array
    {
        return [];
    }

    /**
     * Optional: define severity level dynamically.
     */
    public function level(Throwable $e): ?string
    {
        return null; // default: 'error'
    }
}
