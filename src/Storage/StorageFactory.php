<?php
declare(strict_types=1);

namespace Marwa\Logger\Storage;

use Marwa\Logger\Contracts\SinkInterface;

final class StorageFactory
{
    /**
     * @param array{
     *   driver?: 'file'|'null'|'kafka'|'db',
     *   path?: string,
     *   prefix?: string,
     *   max_bytes?: int|string,
     *   retention_days?: int|string|null,
     *   max_files?: int|string|null
     * } $opts
     */
    public static function make(array $opts): SinkInterface
    {
        $driver = strtolower((string)($opts['driver'] ?? 'file'));

        if ($driver === 'file') {
            $path  = (string)($opts['path'] ?? (sys_get_temp_dir() . '/php-logs'));
            $pref  = (string)($opts['prefix'] ?? 'app');
            $bytes = self::bytes($opts['max_bytes'] ?? 10_485_760);
            $retentionDays = self::nullableInt($opts['retention_days'] ?? null);
            $maxFiles = self::nullableInt($opts['max_files'] ?? null);

            return new FileSink($path, $pref, $bytes, $retentionDays, $maxFiles);
        }

        // Future:
        // if ($driver === 'kafka') return new KafkaSink(...);
        // if ($driver === 'db')    return new DbSink(...);

        return new class implements SinkInterface {
            public function write(string $formatted, string $dateSuffix): void {}
        };
    }

    private static function bytes(int|string $v): int
    {
        if (is_int($v)) return $v;
        if (is_numeric($v)) return (int)$v;
        if (!preg_match('/^\s*(\d+)\s*([kmgt]?b?)?\s*$/i', $v, $m)) return 10_485_760;
        $n = (int)$m[1]; $u = strtolower($m[2] ?? '');
        return match ($u) {
            'k','kb' => $n*1024, 'm','mb' => $n*1024*1024, 'g','gb' => $n*1024*1024*1024,
            't','tb' => (int)($n*1024*1024*1024*1024), default => $n,
        };
    }

    private static function nullableInt(int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
