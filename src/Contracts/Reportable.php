<?php

declare(strict_types=1);

namespace Marwa\Logging\Contracts;

use Psr\Log\LoggerInterface;

/**
 * Optional interface your domain exceptions can implement to influence reporting.
 */
interface Reportable
{
    /**
     * Handle reporting yourself. Return true to skip default logging.
     */
    public function report(LoggerInterface $logger): bool;

    /**
     * Extra context to merge into the log record.
     * Should be small and serializable.
     *
     * @return array<string,mixed>
     */
    public function context(): array;

    /**
     * Optional PSR-3 level override (e.g., "warning", "error", "critical").
     */
    public function level(): ?string;
}
