<?php

namespace Codewiser\HttpCacheControl\Traits;

use Codewiser\HttpCacheControl\Contracts\CacheControlled;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidate cache on model change.
 *
 * @mixin Model
 */
trait InvalidatesCache
{
    protected static function bootInvalidatesCache(): void
    {
        static::saved(function (CacheControlled $object) {
            $object->cache()->invalidate();
        });
        static::deleted(function (CacheControlled $object) {
            $object->cache()->invalidate();
        });
        if (method_exists(static::class, 'restored')) {
            // For SoftDelete
            static::restored(function (CacheControlled $object) {
                $object->cache()->invalidate();
            });
        }
    }
}
