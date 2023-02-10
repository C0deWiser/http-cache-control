<?php

namespace Codewiser\HttpCacheControl\Contracts;

use Codewiser\HttpCacheControl\TaggedCache;

/**
 * Object with tagged cache.
 */
interface CacheControlled
{
    /**
     * Object tagged cache instance.
     */
    public function cache(): TaggedCache;
}
