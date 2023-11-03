# HTTP Cache-Control for Laravel

This package provides solution to work with HTTP Cache-Control headers. Resources are cached and then invalidates on Eloquent events.

## Cache-Control HTTP Headers

### If-None-Matched

Once server calculates unique hash of response and returns it in `ETag` header, then User-Agent may send next request with `If-None-Matched` header.

If hash was not changed, server may respond with `304` status, meaning the resource was not changed and User-Agent may reuse previous response.

### If-Modified-Since

If server responds with `Last-Modified` header, then User-Agent may send next request with `If-Modified-Since` header.

If datetime was not changed, server may respond with `304` status, meaning the resource was not changed and User-Agent may reuse previous response.

## Prepare models

`CacheControl` uses cache to keep headers values. Then model is 
changed the associated cache must be invalidated.

Here is example of implementation. Classes share cache tag, so changing any 
Model invalidates shared cache.

```php
use \Illuminate\Support\Facades\Cache;
use \Illuminate\Cache\TaggedCache;

class User extends Model
{
    protected static function booted(): void
    {
        static::saved(function (User $user) {
            $user::cache()->flush();
        });
        // etc...
    }
    
    public static function cache(): TaggedCache
    {
        return Cache::tags(['user', 'order']);
    }
}

class Order extends Model
{
    protected static function booted(): void
    {
        static::saved(function (Order $order) {
            $order::cache()->flush();
        });
        // etc...
    }
    
    public static function cache(): TaggedCache
    {
        return Cache::tags(['order', 'user']);
    }
}
```

## Usage

`CacheControl` class analyzes request headers and creates response with proper headers.

```php
use \Codewiser\HttpCacheControl\CacheControl;

public function index(Request $request)
{
    return CacheControl::make(Order::cache(), function {
        return OrderResource::collection(Order::all());
    })
    ->etag()
    // the same as
    ->etag(fn($response) => md5($response->getContent()));
}

public function show(Request $request, Order $order)
{
    return CacheControl::make($order::cache(), function() {
        return OrderResource::make($order)
    })
    ->lastModified(fn() => $order->updated_at);
}
```

### Private Cache

If controller response must not be shared across users, you should instruct `CacheControl`:

```php
use \Codewiser\HttpCacheControl\CacheControl;

public function index(Request $request)
{
    return CacheControl::make(Order::cache(), function(Request $request) {
        return OrderResource::collection(Order::whereBelongsTo($request->user()))
    })
    ->private($request->user())
    ->etag();
}
```

