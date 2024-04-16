<?php

namespace Codewiser\HttpCacheControl\Traits;

use Codewiser\HttpCacheControl\Contracts\Cacheable;

/**
 * Invalidate cache on model change.
 *
 */
trait InvalidatesCache
{
    protected static function bootInvalidatesCache(): void
    {
        if (method_exists(static::class, 'saved')) {
            call_user_func([static::class, 'saved'],
                fn(Cacheable $object) => $object->cache()->clear());
        }
        if (method_exists(static::class, 'deleted')) {
            call_user_func([static::class, 'deleted'],
                fn(Cacheable $object) => $object->cache()->clear());
        }
        if (method_exists(static::class, 'restored')) {
            call_user_func([static::class, 'restored'],
                fn(Cacheable $object) => $object->cache()->clear());
        }
    }
}
