<?php

declare(strict_types=1);

namespace Marwa\Logger\Tests;

use Marwa\Logger\Storage\FileSink;
use PHPUnit\Framework\TestCase;

final class FileSinkTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/marwa-logger-tests-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tmpDir)) {
            return;
        }

        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->tmpDir);
    }

    public function testWriteCreatesDirectoryAndLogFile(): void
    {
        $sink = new FileSink($this->tmpDir, 'app', 1024);
        $sink->write("{\"message\":\"ok\"}\n", '2026-03-28');

        $path = $this->tmpDir . '/app-2026-03-28.log';

        self::assertFileExists($path);
        self::assertSame("{\"message\":\"ok\"}\n", file_get_contents($path));
    }

    public function testRotationUsesUniqueBackupName(): void
    {
        $sink = new FileSink($this->tmpDir, 'app', 40);
        $date = '2026-03-28';

        $sink->write(str_repeat('a', 30) . "\n", $date);
        $sink->write(str_repeat('b', 30) . "\n", $date);

        $activePath = $this->tmpDir . '/app-' . $date . '.log';
        $rotated = glob($this->tmpDir . '/app-' . $date . '_*.log') ?: [];

        self::assertCount(1, $rotated);
        self::assertMatchesRegularExpression('/_[0-9]{6}-[A-Fa-f0-9.]+\.log$/', basename($rotated[0]));
        self::assertFileExists($activePath);
        self::assertSame(str_repeat('b', 30) . "\n", file_get_contents($activePath));
        self::assertSame(str_repeat('a', 30) . "\n", file_get_contents($rotated[0]));
    }

    public function testRetentionDaysRemovesExpiredFiles(): void
    {
        mkdir($this->tmpDir, 0775, true);

        $expired = $this->tmpDir . '/app-2026-03-20.log';
        $fresh = $this->tmpDir . '/app-2026-03-28.log';

        file_put_contents($expired, "old\n");
        file_put_contents($fresh, "fresh\n");

        touch($expired, time() - (3 * 86400));
        touch($fresh, time());

        $sink = new FileSink($this->tmpDir, 'app', 1024, 1, null);
        $sink->write("{\"message\":\"new\"}\n", '2026-03-29');

        self::assertFileDoesNotExist($expired);
        self::assertFileExists($fresh);
        self::assertFileExists($this->tmpDir . '/app-2026-03-29.log');
    }

    public function testMaxFilesKeepsNewestLogsOnly(): void
    {
        mkdir($this->tmpDir, 0775, true);

        $oldest = $this->tmpDir . '/app-2026-03-26.log';
        $middle = $this->tmpDir . '/app-2026-03-27.log';

        file_put_contents($oldest, "oldest\n");
        file_put_contents($middle, "middle\n");

        touch($oldest, time() - 20);
        touch($middle, time() - 10);

        $sink = new FileSink($this->tmpDir, 'app', 1024, null, 2);
        $sink->write("{\"message\":\"new\"}\n", '2026-03-28');

        self::assertFileDoesNotExist($oldest);
        self::assertFileExists($middle);
        self::assertFileExists($this->tmpDir . '/app-2026-03-28.log');
    }
}
