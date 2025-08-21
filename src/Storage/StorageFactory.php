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
     *   max_bytes?: int|string
     * } $opts
     */
    public static function make(array $opts): SinkInterface
    {
        $driver = strtolower((string)($opts['driver'] ?? 'file'));

        if ($driver === 'file') {
            $path  = (string)($opts['path'] ?? (sys_get_temp_dir() . '/php-logs'));
            $pref  = (string)($opts['prefix'] ?? 'app');
            $bytes = self::bytes($opts['max_bytes'] ?? 10_485_760);
            return new FileSink($path, $pref, $bytes);
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
        if (!is_string($v)) return 10_485_760;
        if (!preg_match('/^\s*(\d+)\s*([kmgt]?b?)?\s*$/i', $v, $m)) return 10_485_760;
        $n = (int)$m[1]; $u = strtolower($m[2] ?? '');
        return match ($u) {
            'k','kb' => $n*1024, 'm','mb' => $n*1024*1024, 'g','gb' => $n*1024*1024*1024,
            't','tb' => (int)($n*1024*1024*1024*1024), default => $n,
        };
    }
}
