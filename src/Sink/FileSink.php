<?php

declare(strict_types=1);

namespace Marwa\Logging\Sink;

use Marwa\Logging\Contracts\SinkInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * File sink with date suffix and size-based rotation.
 * Rotation: prefix-YYYY-MM-DD.log -> prefix-YYYY-MM-DD_HHMMSS.log
 */
final class FileSink implements SinkInterface
{
    public function __construct(
        private readonly Filesystem $fs,
        private readonly string $basePath,
        private readonly string $filePrefix,
        private readonly int $maxBytes // from Settings
    ) {}

    public function write(string $formatted, string $dateSuffix = ''): void
    {
        $dir  = rtrim($this->basePath, '/');
        $file = $dir . '/' . $this->filePrefix . ($dateSuffix ? "-{$dateSuffix}" : '') . '.log';

        if (!$this->fs->exists($dir)) {
            $this->fs->mkdir($dir, 0775);
        }

        $incoming = strlen($formatted);
        $current  = $this->filesizeSafe($file);

        if ($current > 0 && ($current + $incoming) > $this->maxBytes && $this->fs->exists($file)) {
            $backup = $dir . '/' . $this->filePrefix
                . ($dateSuffix ? "-{$dateSuffix}" : '')
                . '_' . gmdate('His') . '.log';
            if ($this->fs->exists($backup)) {
                $backup = $dir . '/' . $this->filePrefix
                    . ($dateSuffix ? "-{$dateSuffix}" : '')
                    . '_' . gmdate('His') . '_' . bin2hex(random_bytes(3)) . '.log';
            }
            $this->fs->rename($file, $backup, true);
            $current = 0;
        }

        $this->fs->appendToFile($file, $formatted);
    }

    private function filesizeSafe(string $file): int
    {
        if (!$this->fs->exists($file)) return 0;
        clearstatcache(true, $file);
        $size = @filesize($file);
        return is_int($size) ? $size : 0;
    }
}
