<?php

declare(strict_types=1);

namespace Marwa\Logging;

use Marwa\Logging\Config\Settings;
use Marwa\Logging\Contracts\LogFormatterInterface;
use Marwa\Logging\Contracts\SinkInterface;
use Marwa\Logging\Support\SensitiveDataFilter;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-3 logger, production-safe, fast, and pluggable.
 */
final class Logger implements LoggerInterface
{
    public function __construct(
        private readonly Settings $settings,
        private readonly SinkInterface $sink,
        private readonly LogFormatterInterface $formatter,
        private readonly SensitiveDataFilter $filter,
        private readonly string $channel = 'app',
    ) {}

    // ---- PSR-3 ----
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, (string)$message, $context);
    }
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT,     (string)$message, $context);
    }
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL,  (string)$message, $context);
    }
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR,     (string)$message, $context);
    }
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING,   (string)$message, $context);
    }
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE,    (string)$message, $context);
    }
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO,      (string)$message, $context);
    }
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG,     (string)$message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        // Prod: ignore user logs unless explicitly marked system-origin.
        if (!$this->settings->acceptUserLogs) {
            $origin = $context['_origin'] ?? 'user';
            if ($origin !== 'system') {
                return;
            }
        }

        $record    = $this->buildRecord((string)$level, (string)$message, $context);
        $formatted = $this->formatter->format($record);
        $this->sink->write($formatted, date('Y-m-d'));
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function buildRecord(string $level, string $message, array $context): array
    {
        $server = $_SERVER ?? [];
        $req = [
            'method' => $server['REQUEST_METHOD'] ?? null,
            'uri'    => $server['REQUEST_URI'] ?? null,
            'host'   => $server['HTTP_HOST'] ?? null,
            'ip'     => $server['REMOTE_ADDR'] ?? null,
            'ua'     => $server['HTTP_USER_AGENT'] ?? null,
        ];

        $trace = [];
        if (!empty($context['_trace']) && is_array($context['_trace'])) {
            $trace = $this->sanitizeTrace($context['_trace']);
        }

        $extra = [
            'php_version' => PHP_VERSION,
            'php_sapi'    => PHP_SAPI,
            'pid'         => getmypid(),
        ];

        // Remove meta keys from context before filtering
        $contextForFilter = array_diff_key($context, array_flip(['_trace', '_origin']));
        $filteredContext  = $this->filter->scrubArray($contextForFilter);

        $file = $filteredContext['file'] ?? null;
        $line = $filteredContext['line'] ?? null;

        $requestId = $server['HTTP_X_REQUEST_ID'] ?? $server['HTTP_X_CORRELATION_ID'] ?? ('r-' . bin2hex(random_bytes(6)));

        return [
            'ts'         => gmdate('c'),
            'level'      => $level,
            'channel'    => $this->channel,
            'env'        => $this->settings->env,
            'app'        => $this->settings->appName,
            'php_sapi'   => PHP_SAPI,
            'pid'        => getmypid(),
            'message'    => $message,
            'request_id' => $requestId,
            'request'    => $req,
            'context'    => $filteredContext,
            'extra'      => $extra,
            'file'       => $file,
            'line'       => $line,
            'trace'      => $trace,
            'errno'      => $filteredContext['errno'] ?? null,
            'etype'      => $filteredContext['type']  ?? null,
            'ecode'      => $filteredContext['code']  ?? null,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $trace
     * @return array<int,array<string,mixed>>
     */
    private function sanitizeTrace(array $trace): array
    {
        $out = [];
        foreach ($trace as $f) {
            $out[] = [
                'file' => $f['file'] ?? null,
                'line' => $f['line'] ?? null,
                'class' => $f['class'] ?? null,
                'type' => $f['type'] ?? null,
                'function' => $f['function'] ?? null,
            ];
        }
        return $out;
    }
}
