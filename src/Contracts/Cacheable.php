<?php

namespace Codewiser\HttpCacheControl\Contracts;

use Psr\SimpleCache\CacheInterface;

/**
 * Object with cache.
 */
interface Cacheable
{
    /**
     * Object cache instance.
     */
    public function cache(): CacheInterface;
}
