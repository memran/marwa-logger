<?php

declare(strict_types=1);

namespace Marwa\Logging\Support;

/**
 * Sanitizes arrays and superglobals based on sensitive key list.
 */
final class SensitiveDataFilter
{
    /** @param array<int,string> $keys */
    public function __construct(private readonly array $keys) {}

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function scrubArray(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $lk = strtolower((string)$k);
            if (in_array($lk, $this->keys, true)) {
                $out[$k] = '[redacted]';
                continue;
            }
            if (is_array($v)) {
                $out[$k] = $this->scrubArray($v);
            } elseif (is_object($v)) {
                $out[$k] = sprintf('[object %s]', $v::class);
            } elseif (is_resource($v)) {
                $out[$k] = '[resource]';
            } else {
                $out[$k] = $this->truncateIfHuge($v);
            }
        }
        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    public function scrubSuperglobals(): array
    {
        $server = $_SERVER ?? [];
        $bodySnippet = null;

        if (isset($server['REQUEST_METHOD']) && strtoupper((string)$server['REQUEST_METHOD']) !== 'GET') {
            $raw = @file_get_contents('php://input');
            if (is_string($raw) && $raw !== '') {
                $bodySnippet = substr($raw, 0, 4000) . (strlen($raw) > 4000 ? '... [truncated]' : '');
            }
        }

        return [
            '_GET'     => $this->scrubArray($_GET   ?? []),
            '_POST'    => $this->scrubArray($_POST  ?? []),
            '_COOKIE'  => $this->scrubArray($_COOKIE ?? []),
            '_SERVER'  => [
                'REQUEST_METHOD' => $server['REQUEST_METHOD'] ?? null,
                'REQUEST_URI'    => $server['REQUEST_URI'] ?? null,
                'HTTP_HOST'      => $server['HTTP_HOST'] ?? null,
                'REMOTE_ADDR'    => $server['REMOTE_ADDR'] ?? null,
                'USER_AGENT'     => $server['HTTP_USER_AGENT'] ?? null,
                'QUERY_STRING'   => $server['QUERY_STRING'] ?? null,
            ],
            'body_snippet' => $bodySnippet,
        ];
    }

    private function truncateIfHuge(mixed $v): mixed
    {
        if (is_string($v) && strlen($v) > 16000) {
            return substr($v, 0, 16000) . '... [truncated]';
        }
        return $v;
    }
}
