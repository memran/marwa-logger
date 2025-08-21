<?php
declare(strict_types=1);

namespace Marwa\Logger\Storage;

use Marwa\Logger\Contracts\SinkInterface;

final class FileSink implements SinkInterface
{
    public function __construct(
        private string $dir,
        private string $prefix,
        private int $maxBytes = 10_485_760 // 10MB
    ) {
        $this->dir = rtrim($this->dir, DIRECTORY_SEPARATOR);
    }

    public function write(string $formatted, string $dateSuffix): void
    {
        $file = "{$this->dir}/{$this->prefix}-{$dateSuffix}.log";
        if (!is_dir($this->dir)) @mkdir($this->dir, 0775, true);

        $this->rotateIfNeeded($file, $formatted);
        @file_put_contents($file, $formatted, FILE_APPEND | LOCK_EX);
    }

    private function rotateIfNeeded(string $file, string $incoming): void
    {
        clearstatcache(true, $file);
        $size = is_file($file) ? (int)@filesize($file) : 0;
        if ($size > 0 && ($size + strlen($incoming)) > $this->maxBytes) {
            $backup = preg_replace('/\.log$/', '_' . gmdate('His') . '.log', $file) ?? ($file . '_' . gmdate('His'));
            @rename($file, $backup);
        }
    }
}
