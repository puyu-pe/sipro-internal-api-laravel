<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Security\Hmac;

use Illuminate\Cache\Repository as CacheRepository;
use PuyuPe\SiproInternalApiCore\Security\Hmac\NonceStoreInterface;

class LaravelCacheNonceStore implements NonceStoreInterface
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ?string $prefix = 'sipro_internal_nonce:'
    ) {
    }

    public function has(string $nonceKey): bool
    {
        return $this->cache->has($this->cacheKey($nonceKey));
    }

    public function put(string $nonceKey, int $ttlSeconds): void
    {
        $ttl = $ttlSeconds > 0 ? $ttlSeconds : 1;

        $this->cache->put($this->cacheKey($nonceKey), true, $ttl);
    }

    private function cacheKey(string $nonceKey): string
    {
        $prefix = $this->prefix ?? '';

        return $prefix . $nonceKey;
    }
}
