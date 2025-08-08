<?php

declare(strict_types=1);

namespace Marwa\Logging\Contracts;

/**
 * Turns a structured record into a formatted string.
 */
interface LogFormatterInterface
{
    /**
     * @param array<string,mixed> $record
     */
    public function format(array $record): string;
}
