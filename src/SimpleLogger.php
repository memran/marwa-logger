<?php

declare(strict_types=1);

namespace Marwa\Logger;

use Marwa\Logger\Contracts\SinkInterface;
use Marwa\Logger\Support\SensitiveDataFilter;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

final class SimpleLogger implements LoggerInterface
{
    /** @var string|null */
    private ?string $requestId = null;

    private const ENV_PRODUCTION = 'production';

    /** @var array<string,int> */
    private const LEVEL_ORDER = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT     => 1,
        LogLevel::CRITICAL  => 2,
        LogLevel::ERROR     => 3,
        LogLevel::WARNING   => 4,
        LogLevel::NOTICE    => 5,
        LogLevel::INFO      => 6,
        LogLevel::DEBUG     => 7,
    ];

    public function __construct(
        private string $appName,
        private string $env,
        private SinkInterface $sink,
        private SensitiveDataFilter $filter,
        private bool $logging = false,
        private string $productionMinLevel = LogLevel::ERROR // log only from this level ↑ in prod
    ) {
        $this->env = self::normalizeEnv($this->env);

        // safety: if someone passes invalid level name
        $this->productionMinLevel = strtolower($this->productionMinLevel);
        if (!isset(self::LEVEL_ORDER[$this->productionMinLevel])) {
            $this->productionMinLevel = LogLevel::ERROR;
        }
    }

    /**
     * Set a request/correlation ID for this logger instance.
     * Call this once in your HTTP middleware and the ID will be added to all logs.
     */
    public function setRequestId(?string $requestId): void
    {
        $this->requestId = $requestId !== '' ? $requestId : null;
    }

    public function emergency(string|Stringable $m, array $c = []): void
    {
        $this->log(LogLevel::EMERGENCY, (string)$m, $c);
    }
    public function alert(string|Stringable $m, array $c = []): void
    {
        $this->log(LogLevel::ALERT,     (string)$m, $c);
    }
    public function critical(string|Stringable $m, array $c = []): void
    {
        $this->log(LogLevel::CRITICAL,  (string)$m, $c);
    }
    public function error(string|Stringable $m, array $c = []): void
    {
        $this->log(LogLevel::ERROR,     (string)$m, $c);
    }
    public function warning(string|Stringable $m, array $c = []): void
    {
        $this->log(LogLevel::WARNING,   (string)$m, $c);
    }
    public function notice(string|Stringable $m, array $c = []): void
    {
        $this->log(LogLevel::NOTICE,    (string)$m, $c);
    }
    public function info(string|Stringable $m, array $c = []): void
    {
        $this->log(LogLevel::INFO,      (string)$m, $c);
    }
    public function debug(string|Stringable $m, array $c = []): void
    {
        $this->log(LogLevel::DEBUG,     (string)$m, $c);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $level = strtolower((string) $level);
        if (!isset(self::LEVEL_ORDER[$level])) {
            throw new \InvalidArgumentException(sprintf('Unsupported log level "%s".', (string) $level));
        }

        // 1) logging switch
        if ($this->logging === false) {
            return;
        }

        // 2) production rules
        if ($this->env === self::ENV_PRODUCTION) {
            // only system-origin
            if (($context['_origin'] ?? 'user') === 'user') {
                return;
            }

            // only important levels
            if (!$this->isProdLevelAllowed((string)$level)) {
                return;
            }
        }

        $server = $_SERVER;

        // final request id priority:
        // 1) context['request_id']
        // 2) instance->requestId (set by middleware)
        // 3) header X-Request-ID / X-Correlation-ID
        $requestId = $context['request_id']
            ?? $this->requestId
            ?? $this->detectRequestId($server);

        $record = [
            'ts'         => gmdate('c'),
            'level'      => (string)$level,
            'app'        => $this->appName,
            'env'        => $this->env,
            'pid'        => getmypid(),
            'php'        => PHP_VERSION,
            'sapi'       => PHP_SAPI,
            'message'    => (string)$message,
            'request_id' => $requestId,
            'request'    => [
                'method' => $server['REQUEST_METHOD'] ?? null,
                'uri'    => $server['REQUEST_URI'] ?? null,
                'host'   => $server['HTTP_HOST'] ?? null,
                'ip'     => $server['REMOTE_ADDR'] ?? null,
                'ua'     => $server['HTTP_USER_AGENT'] ?? null,
            ],
            'context'    => $this->filter->scrub($context),
        ];

        $json = $this->encodeRecord($record) . PHP_EOL;
        $this->sink->write($json, gmdate('Y-m-d'));
    }

    /**
     * Detect request-id from common headers if available.
     * @param array<string,mixed> $server
     */
    private function detectRequestId(array $server): ?string
    {
        return $server['HTTP_X_REQUEST_ID']
            ?? $server['HTTP_X_CORRELATION_ID']
            ?? null;
    }

    /**
     * Check if this level is allowed in production based on $productionMinLevel.
     */
    private function isProdLevelAllowed(string $level): bool
    {
        $curr = self::LEVEL_ORDER[$level] ?? self::LEVEL_ORDER[LogLevel::DEBUG];
        $min  = self::LEVEL_ORDER[$this->productionMinLevel];
        // lower number = higher severity
        return $curr <= $min;
    }

    /**
     * @param array<string,mixed> $record
     */
    private function encodeRecord(array $record): string
    {
        try {
            return json_encode(
                $record,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (\JsonException) {
            $sanitized = $this->sanitizeForJson($record);

            try {
                return json_encode(
                    $sanitized,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            } catch (\JsonException $e) {
                throw new \RuntimeException('Unable to encode log record as JSON.', 0, $e);
            }
        }
    }

    private static function normalizeEnv(string $env): string
    {
        return match (strtolower(trim($env))) {
            'prod', 'production', 'live' => self::ENV_PRODUCTION,
            default => strtolower(trim($env)),
        };
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeForJson(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizeForJson($item);
            }

            return $sanitized;
        }

        if (is_string($value) && !preg_match('//u', $value)) {
            $hex = substr(bin2hex($value), 0, 64);
            $suffix = strlen($value) > 32 ? '...' : '';

            return sprintf('[invalid utf-8:%s%s]', $hex, $suffix);
        }

        return $value;
    }
}
