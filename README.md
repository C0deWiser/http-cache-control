# HTTP Cache-Control with Tagged Cache for Laravel

This package provides solution to work with HTTP Cache-Control headers. Resources are cached and then invalidates on Eloquent events.

## Cache-Control HTTP Headers

### If-None-Matched

Once server calculates unique hash of response and returns it in `ETag` header, then User-Agent may send next request with `If-None-Matched` header.

If hash was not changed, server may respond with `304` status, meaning the resource was not changed and User-Agent may reuse previous response.

### If-Modified-Since

If server responds with `Last-Modified` header, then User-Agent may send next request with `If-Modified-Since` header.

If datetime was not changed, server may respond with `304` status, meaning the resource was not changed and User-Agent may reuse previous response.

## CacheControlled Interface

`CacheControlled` interface should be implemented by every `Model`, that are planned to be used with HTTP Cache-Control. It requires tagged cache instance.

## InvalidatesCache

`InvalidatesCache` trait should be used by every `CacheControlled` Model.

Here is example of usage. Classes share cache tag, so changing any Model invalidates shared cache. 

```php
use \Codewiser\HttpCacheControl\TaggedCache;
use \Codewiser\HttpCacheControl\Contracts\CacheControlled;
use \Codewiser\HttpCacheControl\Traits\InvalidatesCache;

class User extends Model implements CacheControlled
{
    use InvalidatesCache;
    
    public function cache(): TaggedCache
    {
        return TaggedCache::for('user')
            ->dependsOn('order');
    }
}

class Order extends Model implements CacheControlled
{
    use InvalidatesCache;
    
    public function cache(): TaggedCache
    {
        return TaggedCache::for('order')
            ->dependsOn('user');
    }
}
```

## Controller

`HttpCacheControl` class analyzes request headers and creates response with proper headers, using Class/Model cache.

`HttpCacheControl::setResponse` method will be invoked only if headers expires or cache is missed.

```php
use \Codewiser\HttpCacheControl\HttpCacheControl;

public function index(Request $request)
{
    return HttpCacheControl::index(Order::class)
        ->setResponse(fn() => OrderResource::collection(Order::all()))
        ->withEtag()
        // the same as
        ->setEtag(fn($response) => md5($response->getContent()));
}

public function show(Request $request, Order $order)
{
    return HttpCacheControl::show($order)
        ->setResponse(fn() => OrderResource::make($order))
        ->setLastModified(fn() => $order->updated_at);
}
```

### Private Cache

If controller response must not be shared across users, you should instruct `HttpCacheControl`:

```php
use \Codewiser\HttpCacheControl\HttpCacheControl;

public function index(Request $request)
{
    return HttpCacheControl::index(Order::class)
        ->setPrivate($request->user())
        ->setResponse(fn() => OrderResource::collection(Order::whereBelongsTo($request->user())))
        ->withEtag();
}
```
