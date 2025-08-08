<?php

declare(strict_types=1);

namespace Marwa\Logging\Config;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Immutable configuration holder.
 * NOTE: This version only consumes a developer-supplied array.
 */
final class Settings
{
    public function __construct(
        public readonly string $appName,
        public readonly string $env,
        public readonly string $logPath,
        public readonly bool   $displayErrors,
        public readonly bool   $acceptUserLogs,
        /** @var array<int,string> */
        public readonly array  $sensitiveKeys,
        public readonly ?CacheItemPoolInterface $cachePool = null,
        public readonly int    $maxLogBytes = 10485760 // 10MB default
    ) {}

    /**
     * Build from an options array.
     *
     * Recognized keys (all optional):
     * - app_name        : string
     * - env             : string ('production'|'staging'|'development'|'local'|...)
     * - log_path        : string (directory)
     * - display_errors  : bool (defaults by env if not given)
     * - accept_user_logs: bool (defaults by env if not given)
     * - sensitive_keys  : array<string> | string (comma-separated)
     * - max_log_bytes   : int | string (bytes; accepts "10MB","5M","10485760")
     */
    public static function make(array $options = [], ?CacheItemPoolInterface $cachePool = null): self
    {
        $opts = self::normalizeOptions($options);

        $env     = $opts['env'] ?: 'production';
        $isDev   = in_array(strtolower($env), ['local', 'dev', 'development'], true);

        $appName = $opts['app_name'] ?: 'app';
        $logPath = rtrim($opts['log_path'] ?: (sys_get_temp_dir() . '/php-logs'), '/');

        $display = ($opts['display_errors'] !== null)
            ? (bool)$opts['display_errors']
            : $isDev;

        $accept  = ($opts['accept_user_logs'] !== null)
            ? (bool)$opts['accept_user_logs']
            : $isDev;

        $keys    = self::normalizeSensitiveKeys($opts['sensitive_keys']);

        $max     = self::toIntBytes($opts['max_log_bytes']);
        if ($max <= 0) $max = 10 * 1024 * 1024; // 10MB fallback

        return new self(
            appName: $appName,
            env: $env,
            logPath: $logPath,
            displayErrors: $display,
            acceptUserLogs: $accept,
            sensitiveKeys: $keys,
            cachePool: $cachePool,
            maxLogBytes: $max
        );
    }

    /* ------------------------- Helpers ------------------------- */

    /**
     * @param array<string,mixed> $options
     * @return array{
     *   app_name: ?string,
     *   env: ?string,
     *   log_path: ?string,
     *   display_errors: ?bool,
     *   accept_user_logs: ?bool,
     *   sensitive_keys: array<int,string>|string|null,
     *   max_log_bytes: int|string|null
     * }
     */
    private static function normalizeOptions(array $options): array
    {
        // Lowercase keys for robustness
        $norm = [];
        foreach ($options as $k => $v) {
            $norm[strtolower((string)$k)] = $v;
        }

        return [
            'app_name'         => self::toStringNullable($norm['app_name'] ?? null),
            'env'              => self::toStringNullable($norm['env'] ?? null),
            'log_path'         => self::toStringNullable($norm['log_path'] ?? null),
            'display_errors'   => self::toBoolNullable($norm['display_errors'] ?? null),
            'accept_user_logs' => self::toBoolNullable($norm['accept_user_logs'] ?? null),
            'sensitive_keys'   => $norm['sensitive_keys'] ?? null,
            'max_log_bytes'    => $norm['max_log_bytes'] ?? null,
        ];
    }

    /** @return array<int,string> */
    private static function normalizeSensitiveKeys(null|array|string $keys): array
    {
        if (is_string($keys) && $keys !== '') {
            $keys = array_map('trim', explode(',', $keys));
        }
        if (!is_array($keys) || !$keys) {
            $keys = [
                'password',
                'passwd',
                'pass',
                'secret',
                'token',
                'api_key',
                'apikey',
                'authorization',
                'cookie',
                'set-cookie',
                'access_token',
                'refresh_token',
                'credit_card',
                'cc',
                'ssn',
                'nid',
                'pin',
                'otp',
                'private_key',
                'client_secret'
            ];
        }
        // normalize + dedupe
        $lower = array_values(array_unique(array_map(
            static fn($k) => strtolower((string)$k),
            $keys
        )));
        return $lower;
    }

    private static function toStringNullable(mixed $v): ?string
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }

    private static function toBoolNullable(mixed $v): ?bool
    {
        if ($v === null) return null;
        if (is_bool($v)) return $v;
        $s = strtolower(trim((string)$v));
        if ($s === '') return null;
        return in_array($s, ['1', 'true', 'yes', 'on'], true) ? true
            : (in_array($s, ['0', 'false', 'no', 'off'], true) ? false : null);
    }

    /**
     * Accepts:
     *  - int bytes
     *  - strings like "10485760", "10MB", "10M", "512k", "512KB"
     */
    private static function toIntBytes(mixed $v): int
    {
        if (is_int($v)) return $v;
        if (is_numeric($v)) return (int)$v;
        if (!is_string($v)) return 0;

        $s = trim($v);
        if ($s === '') return 0;

        // Match <number><unit, unit optional
        if (!preg_match('/^\s*([0-9]+)\s*([kmgt]?b?)?\s*$/i', $s, $m)) {
            return 0;
        }
        $num  = (int)$m[1];
        $unit = strtolower($m[2] ?? '');

        return match ($unit) {
            'k', 'kb' => $num * 1024,
            'm', 'mb' => $num * 1024 * 1024,
            'g', 'gb' => $num * 1024 * 1024 * 1024,
            't', 'tb' => (int)($num * 1024 * 1024 * 1024 * 1024),
            default   => $num, // bytes
        };
    }
}
