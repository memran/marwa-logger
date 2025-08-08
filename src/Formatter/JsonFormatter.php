<?php

declare(strict_types=1);

namespace Marwa\Logging\Formatter;

use Marwa\Logging\Contracts\LogFormatterInterface;

/**
 * JSON Lines (NDJSON) formatter.
 * - One JSON object per log entry
 * - UTF-8 safe (no escaping of slashes or Unicode)
 * - Optional pretty print (dev)
 * - Graceful fallback on encoding failure
 */
final class JsonFormatter implements LogFormatterInterface
{
    private int $flags;

    /**
     * @param bool $pretty When true, outputs pretty-printed JSON (useful in dev).
     */
    public function __construct(bool $pretty = false)
    {
        $this->flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            | ($pretty ? JSON_PRETTY_PRINT : 0);
    }

    /**
     * @param array<string,mixed> $record
     */
    public function format(array $record): string
    {
        // Ensure minimal required fields exist and types are sane
        $record['ts']      = $record['ts']      ?? gmdate('c');
        $record['level']   = isset($record['level'])   ? (string)$record['level']   : 'info';
        $record['message'] = isset($record['message']) ? (string)$record['message'] : '';

        $json = json_encode($record, $this->flags);
        if ($json === false) {
            // Fallback record if something inside $record is not encodable
            $json = json_encode([
                'ts'      => gmdate('c'),
                'level'   => 'error',
                'message' => 'log_encode_failed',
                'context' => [
                    'json_last_error' => json_last_error_msg(),
                ],
            ], $this->flags);
        }

        return $json . PHP_EOL;
    }
}
