# HTTP Cache-Control for Laravel

This package provides solution to work with HTTP Cache-Control headers.
Resources are cached and then invalidates on Eloquent events. Server may respond
without making database requests, just using cache values.

## Prepare models

`CacheControl` uses cache to keep headers values. Then model is changed the
associated cache must be invalidated.

The models must implement `\Codewiser\HttpCacheControl\Contracts\Cacheable`.

Here is example of implementation. Classes share cache tag, so changing any
Model invalidates shared cache.

```php
use Codewiser\HttpCacheControl\Contracts\Cacheable;
use Codewiser\HttpCacheControl\Observers\InvalidatesCache;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\CacheInterface;

#[ObservedBy(InvalidatesCache::class)]
class User extends Model implements Cacheable
{
    public function cache(): CacheInterface
    {
        return Cache::tags(['user', 'order']);
    }
}

#[ObservedBy(InvalidatesCache::class)]
class Order extends Model implements Cacheable
{
    public function cache(): CacheInterface
    {
        return Cache::tags(['order', 'user']);
    }
}
```

## Usage

`CacheControl` class analyzes request headers and creates response with proper
headers.

First argument must be a `\Psr\SimpleCache\CacheInterface`,
or `\Codewiser\HttpCacheControl\Contracts\Cacheable`, or classname of a Model,
that implements that interface.

Second argument is a callback, that should return response content. This
callback would be called only if necessary.

### `Cache-Control` header

You may set any `Cache-Control` directives you like:

```php
use Codewiser\HttpCacheControl\CacheControl;
use Codewiser\HttpCacheControl\CacheControlHeader;

public function index(Request $request)
{
    return CacheControl::make(
        Order::class, 
        fn() => OrderResource::collection(Order::all())
    )
        ->cacheControl(fn(Request $request) => new CacheControlHeader(
            public: true,
            max_age: 1800,
            must_revalidate: true,
        ));
}
```
> If no `public` or `private` directives was not set, it would be `private` by
> default.

> Nothing would be cached on server in this case.

### `Expires` header

Or you may set only `Expires` header:

```php
use Codewiser\HttpCacheControl\CacheControl;
use Codewiser\HttpCacheControl\CacheControlHeader;

public function index(Request $request)
{
    return CacheControl::make(
        Order::class, 
        fn() => OrderResource::collection(Order::all())
    )
        ->expires(now()->addHour());
}
```

> Nothing would be cached on server in this case.

### Caching entire response

You may want to cache the entire response:

```php
use Codewiser\HttpCacheControl\CacheControl;
use Codewiser\HttpCacheControl\CacheControlHeader;

public function index(Request $request)
{
    return CacheControl::make(
        Order::class, 
        fn() => OrderResource::collection(Order::all())
    )
        ->remember()
        ->cacheControl(new CacheControlHeader(
            public: true,
            max_age: now()->addHour(),
            must_revalidate: true,
        ));
}
```

> Use with care. Note, that cache may become huge.

### Private Cache

If controller response must not be shared across users, you should
set `Cache-Control: private` directive.

```php
use Codewiser\HttpCacheControl\CacheControl;
use Codewiser\HttpCacheControl\CacheControlHeader;

public function index(Request $request)
{
    return CacheControl::make(
        Order::class, 
        fn(Request $request) => OrderResource::collection(
            Order::query()->whereBelongsTo($request->user())->get()
        )
    )
        ->cacheControl(new CacheControlHeader(
            private: true,
            max_age: new DateInterval('PT1H'),
            must_revalidate: true,
        ));
}
```

### `Vary` header

`Vary` headers describes a list of request headers, that matters for caching.

For example, your application supports different languages, so cache depends
on `Accept-Language` request header:

```php
use Codewiser\HttpCacheControl\CacheControl;
use Codewiser\HttpCacheControl\CacheControlHeader;

public function index(Request $request)
{
    return CacheControl::make(
        Order::class, 
        fn() => OrderResource::collection(Order::all())
    )
        ->vary('Accept-Language')
        ->cacheControl(new CacheControlHeader(
            public: true,
            max_age: 1800,
            must_revalidate: true,
        ));
}
```

> Note, that web-server may append more Vary headers values. It
> is `Accept-Encoding` as usual.

### Conditional headers

Controller may respond with `ETag` and/or `Last-Modified` header, so the next
request may bring `If-None-Match` or `If-Modified-Since` headers, that makes it
conditional.

```php
use Codewiser\HttpCacheControl\CacheControl;
use Codewiser\HttpCacheControl\CacheControlHeader;

public function index(Request $request)
{
    return CacheControl::make(
        Order::class, 
        fn() => OrderResource::collection(Order::all())
    )
        ->cacheControl(['public' => true])
        // Implicit
        ->etag()
        // Or explicit
        ->etag(fn() => custom_etag_calculation(Order::all()));
}
```

In this example local cache stores only `ETag` value. That would be enough to
check future requests with `If-None-Match`.

This way is the most cache careful.

```php
use Codewiser\HttpCacheControl\CacheControl;
use Codewiser\HttpCacheControl\CacheControlHeader;

public function index(Request $request)
{
    return CacheControl::make(
        Order::class, 
        fn() => OrderResource::collection(Order::all())
    )
        ->cacheControl(['public' => true])
        // Return timestamp or DateTimeInterface
        ->lastModified(fn() => Order::all()->max('updated_at'));
}
```
