<?php
declare(strict_types=1);

namespace Marwa\Logger\Contracts;

interface SinkInterface
{
    /**
     * Append a formatted string (e.g., JSON line) to storage.
     * @param string $formatted One record (already formatted) + newline if needed
     * @param string $dateSuffix Date suffix for file naming (e.g., '2025-08-21')
     */
    public function write(string $formatted, string $dateSuffix): void;
}
