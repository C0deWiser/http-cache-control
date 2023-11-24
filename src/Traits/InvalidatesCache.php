<?php

namespace Codewiser\HttpCacheControl\Traits;

use Codewiser\HttpCacheControl\Contracts\Cacheable;
use Codewiser\HttpCacheControl\Contracts\CacheControlled;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Invalidate cache on model change.
 *
 * @mixin Model
 * @mixin SoftDeletes
 */
trait InvalidatesCache
{
    protected static function bootInvalidatesCache(): void
    {
        static::saved(function (Cacheable $object) {
            $object->cache()->clear();
        });
        static::deleted(function (Cacheable $object) {
            $object->cache()->clear();
        });
        if (method_exists(static::class, 'restored')) {
            // For SoftDelete
            static::restored(function (Cacheable $object) {
                $object->cache()->clear();
            });
        }
    }
}
