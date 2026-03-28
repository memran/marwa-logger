<?php
declare(strict_types=1);

namespace Marwa\Logger\Storage;

use Marwa\Logger\Contracts\SinkInterface;

final class FileSink implements SinkInterface
{
    public function __construct(
        private string $dir,
        private string $prefix,
        private int $maxBytes = 10_485_760, // 10MB
        private ?int $retentionDays = null,
        private ?int $maxFiles = null
    ) {
        $this->dir = rtrim($this->dir, DIRECTORY_SEPARATOR);
    }

    public function write(string $formatted, string $dateSuffix): void
    {
        $file = $this->dir . DIRECTORY_SEPARATOR . "{$this->prefix}-{$dateSuffix}.log";
        $this->ensureDirectoryExists();

        $this->rotateIfNeeded($file, strlen($formatted));
        if (file_put_contents($file, $formatted, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write log file "%s".', $file));
        }

        $this->cleanupOldFiles();
    }

    private function rotateIfNeeded(string $file, int $incomingBytes): void
    {
        clearstatcache(true, $file);
        $size = is_file($file) ? (int) filesize($file) : 0;
        if ($size > 0 && ($size + $incomingBytes) > $this->maxBytes) {
            $backup = $this->buildRotationPath($file);
            if (!rename($file, $backup)) {
                throw new \RuntimeException(sprintf('Unable to rotate log file "%s".', $file));
            }
        }
    }

    private function ensureDirectoryExists(): void
    {
        if (is_dir($this->dir)) {
            return;
        }

        if (!mkdir($concurrentDirectory = $this->dir, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create log directory "%s".', $this->dir));
        }
    }

    private function buildRotationPath(string $file): string
    {
        $suffix = gmdate('His') . '-' . $this->rotationToken();

        return preg_replace('/\.log$/', '_' . $suffix . '.log', $file) ?? ($file . '_' . $suffix);
    }

    private function rotationToken(): string
    {
        try {
            return bin2hex(random_bytes(4));
        } catch (\Throwable) {
            return uniqid('', true);
        }
    }

    private function cleanupOldFiles(): void
    {
        $files = $this->matchingLogFiles();

        if ($this->retentionDays !== null && $this->retentionDays >= 0) {
            $cutoff = time() - ($this->retentionDays * 86400);

            foreach ($files as $index => $file) {
                $mtime = @filemtime($file);
                if ($mtime !== false && $mtime < $cutoff && is_file($file)) {
                    @unlink($file);
                    unset($files[$index]);
                }
            }
        }

        if ($this->maxFiles !== null && $this->maxFiles > 0) {
            $files = array_values(array_filter($files, 'is_file'));

            usort(
                $files,
                static fn (string $left, string $right): int => (filemtime($right) ?: 0) <=> (filemtime($left) ?: 0)
            );

            foreach (array_slice($files, $this->maxFiles) as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function matchingLogFiles(): array
    {
        $pattern = $this->dir . DIRECTORY_SEPARATOR . $this->prefix . '-*.log';
        $files = glob($pattern);

        if ($files === false) {
            return [];
        }

        return array_values(array_filter($files, 'is_string'));
    }
}
