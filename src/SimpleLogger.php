<?php
declare(strict_types=1);

namespace Marwa\Logger;

use Marwa\Logger\Contracts\SinkInterface;
use Marwa\Logger\Support\SensitiveDataFilter;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class SimpleLogger implements LoggerInterface
{
    public function __construct(
        private string $appName,
        private string $env,
        private SinkInterface $sink,
        private SensitiveDataFilter $filter,
        private bool $logging = false
    ) {}

    public function emergency($m, array $c = []): void { $this->log(LogLevel::EMERGENCY, (string)$m, $c); }
    public function alert($m, array $c = []): void     { $this->log(LogLevel::ALERT,     (string)$m, $c); }
    public function critical($m, array $c = []): void  { $this->log(LogLevel::CRITICAL,  (string)$m, $c); }
    public function error($m, array $c = []): void     { $this->log(LogLevel::ERROR,     (string)$m, $c); }
    public function warning($m, array $c = []): void   { $this->log(LogLevel::WARNING,   (string)$m, $c); }
    public function notice($m, array $c = []): void    { $this->log(LogLevel::NOTICE,    (string)$m, $c); }
    public function info($m, array $c = []): void      { $this->log(LogLevel::INFO,      (string)$m, $c); }
    public function debug($m, array $c = []): void     { $this->log(LogLevel::DEBUG,     (string)$m, $c); }

    public function log($level, $message, array $context = []): void
    {
        if($this->logging === false) {
            return; // Logging is disabled
        }

        if($this->env === 'production' && ($level === LogLevel::DEBUG || $level === LogLevel::INFO)) {
            return; // Ignore debug logs in production
        }
        if($this->env === 'production' && isset($context['_origin']) && $context['_origin'] === 'user') {
            return; // Ignore user-origin logs in production
        }
        $server = $_SERVER ?? [];
        $record = [
            'ts'       => gmdate('c'),
            'level'    => (string)$level,
            'app'      => $this->appName,
            'env'      => $this->env,
            'pid'      => getmypid(),
            'php'      => PHP_VERSION,
            'sapi'     => PHP_SAPI,
            'message'  => (string)$message,
            'request'  => [
                'method' => $server['REQUEST_METHOD'] ?? null,
                'uri'    => $server['REQUEST_URI'] ?? null,
                'host'   => $server['HTTP_HOST'] ?? null,
                'ip'     => $server['REMOTE_ADDR'] ?? null,
                'ua'     => $server['HTTP_USER_AGENT'] ?? null,
            ],
            'context'  => $this->filter->scrub($context),
        ];

        $json = json_encode($record, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->sink->write($json, gmdate('Y-m-d'));
    }
}
