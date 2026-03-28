<?php

declare(strict_types=1);

namespace Marwa\Logger\Tests;

use Marwa\Logger\Contracts\SinkInterface;
use Marwa\Logger\SimpleLogger;
use Marwa\Logger\Support\SensitiveDataFilter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class SimpleLoggerTest extends TestCase
{
    public function testProdAliasUsesProductionFiltering(): void
    {
        $sink = new InMemorySink();
        $logger = new SimpleLogger(
            appName: 'app',
            env: 'prod',
            sink: $sink,
            filter: new SensitiveDataFilter(),
            logging: true,
            productionMinLevel: LogLevel::ERROR
        );

        $logger->info('ignored user log', []);
        $logger->error('kept system log', ['_origin' => 'system']);

        self::assertCount(1, $sink->writes);
        self::assertStringContainsString('"message":"kept system log"', $sink->writes[0]);
    }

    public function testInvalidLevelThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $logger = new SimpleLogger(
            appName: 'app',
            env: 'dev',
            sink: new InMemorySink(),
            filter: new SensitiveDataFilter(),
            logging: true
        );

        $logger->log('verbose', 'unsupported');
    }

    public function testSensitiveFieldsAreRedactedRecursively(): void
    {
        $sink = new InMemorySink();
        $logger = new SimpleLogger(
            appName: 'app',
            env: 'dev',
            sink: $sink,
            filter: new SensitiveDataFilter(),
            logging: true
        );

        $logger->info('payload', [
            'token' => 'abc',
            'nested' => ['password' => 'secret'],
        ]);

        self::assertCount(1, $sink->writes);
        self::assertStringContainsString('"token":"[redacted]"', $sink->writes[0]);
        self::assertStringContainsString('"password":"[redacted]"', $sink->writes[0]);
    }

    public function testInvalidUtf8IsSanitizedBeforeEncoding(): void
    {
        $sink = new InMemorySink();
        $logger = new SimpleLogger(
            appName: 'app',
            env: 'dev',
            sink: $sink,
            filter: new SensitiveDataFilter(),
            logging: true
        );

        $logger->info('payload', [
            'raw' => "\xB1\x31",
        ]);

        self::assertCount(1, $sink->writes);
        self::assertStringContainsString('"raw":"[invalid utf-8:', $sink->writes[0]);
        self::assertStringNotContainsString("\xB1\x31", $sink->writes[0]);
    }
}

final class InMemorySink implements SinkInterface
{
    /** @var list<string> */
    public array $writes = [];

    public function write(string $formatted, string $dateSuffix): void
    {
        $this->writes[] = $formatted;
    }
}
