<?php

namespace Src\Utils;

class TokenCache {
    private array $cache = [];

    /**
     * Creates a new instance of TokenCache for token validation.
     * @param int $cacheTTL Cache time-to-live in seconds.
     */
    public function __construct(private int $cacheTTL = 300) {}

    public function set(string $token, \stdClass $decodedToken): void
    {
        $this->cache[$token] = [
            'data' => $decodedToken,
            'expires_at' => time() + $this->cacheTTL
        ];
    }

    public function get(string $token): ?\stdClass
    {
        if (isset($this->cache[$token])) {
            if ($this->cache[$token]['expires_at'] > time()) {
                return $this->cache[$token]['data'];
            } else {
                unset($this->cache[$token]);
            }
        }

        return null;
    }
}