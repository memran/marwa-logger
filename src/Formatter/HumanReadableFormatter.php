<?php

declare(strict_types=1);

namespace Marwa\Logging\Formatter;

use Marwa\Logging\Contracts\LogFormatterInterface;
use Psr\Log\LogLevel;

/**
 * Fast, developer-friendly, multiline text formatter.
 */
final class HumanReadableFormatter implements LogFormatterInterface
{
    /** @param array<string,mixed> $r */
    public function format(array $r): string
    {
        $head = sprintf(
            "[%s] %s.%s (%s) %s",
            $r['ts'] ?? gmdate('c'),
            $r['level'] ?? LogLevel::INFO,
            $r['channel'] ?? 'app',
            $r['env'] ?? 'production',
            $r['message'] ?? ''
        );

        $lines = [
            $head,
            sprintf(
                "app=%s pid=%s request_id=%s php_sapi=%s",
                $r['app'] ?? 'app',
                $r['pid'] ?? getmypid(),
                $r['request_id'] ?? '-',
                $r['php_sapi'] ?? PHP_SAPI
            ),
        ];

        if (!empty($r['file']) || !empty($r['line'])) {
            $lines[] = "at {$r['file']}:{$r['line']}";
        }
        if (!empty($r['errno'])) $lines[] = "errno: {$r['errno']}";
        if (!empty($r['etype'])) $lines[] = "exception: {$r['etype']} (code {$r['ecode']})";

        if (!empty($r['request'])) {
            $req = $r['request'];
            $lines[] = sprintf(
                "http %s %s host=%s ip=%s ua=%s",
                $req['method'] ?? '-',
                $req['uri'] ?? '-',
                $req['host'] ?? '-',
                $req['ip'] ?? '-',
                $req['ua'] ?? '-'
            );
        }

        if (!empty($r['context'])) $lines[] = "context: " . self::pretty($r['context']);
        if (!empty($r['extra']))   $lines[] = "extra: "   . self::pretty($r['extra']);

        $lines[] = sprintf(
            "mem_used=%.2fMB mem_peak=%.2fMB",
            (memory_get_usage(true) / 1048576),
            (memory_get_peak_usage(true) / 1048576)
        );

        if (!empty($r['trace']) && is_array($r['trace'])) {
            $fmt = [];
            $i = 0;
            foreach ($r['trace'] as $t) {
                $fmt[] = sprintf(
                    "#%d %s:%s %s%s%s",
                    $i++,
                    $t['file'] ?? '-',
                    $t['line'] ?? '-',
                    $t['class'] ?? '',
                    $t['type'] ?? '',
                    $t['function'] ?? ''
                );
                if ($i >= 5) break;
            }
            $lines[] = "trace:\n  " . implode("\n  ", $fmt);
        }

        return implode("\n", $lines) . "\n\n";
    }

    private static function pretty(mixed $v): string
    {
        if (is_scalar($v) || $v === null) return (string)json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
