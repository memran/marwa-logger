<?php
declare(strict_types=1);

namespace Marwa\Logger\Support;

final class SensitiveDataFilter
{
    /** @var array<int,string> */
    private array $keys;

    /**
     * @param array<int,string> $keys e.g. ['password','token','authorization']
     */
    public function __construct(array $keys = [])
    {
        $default = [
            'password','passwd','pass','secret','token','api_key','apikey',
            'authorization','cookie','set-cookie','access_token','refresh_token',
            'credit_card','cc','ssn','nid','pin','otp','private_key','client_secret',
        ];
        $keys = $keys ?: $default;
        $this->keys = array_values(array_unique(array_map('strtolower', $keys)));
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function scrub(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $lk = strtolower((string)$k);
            if (in_array($lk, $this->keys, true)) {
                $out[$k] = '[redacted]';
                continue;
            }
            if (is_array($v)) {
                $out[$k] = $this->scrub($v);
            } elseif (is_object($v)) {
                $out[$k] = '[object ' . $v::class . ']';
            } elseif (is_resource($v)) {
                $out[$k] = '[resource]';
            } else {
                $out[$k] = (is_string($v) && strlen($v) > 16000)
                    ? (substr($v, 0, 16000) . '... [truncated]')
                    : $v;
            }
        }
        return $out;
    }
}
