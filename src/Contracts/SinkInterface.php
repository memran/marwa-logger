<?php

declare(strict_types=1);

namespace Marwa\Logging\Contracts;

/**
 * Abstraction for log storage (file today; Kafka/DB later).
 */
interface SinkInterface
{
    /**
     * Persist a formatted record. Implementations must be fast and robust.
     */
    public function write(string $formatted, string $dateSuffix = ''): void;
}
