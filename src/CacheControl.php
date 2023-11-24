<?php

namespace Codewiser\HttpCacheControl;

use Closure;
use Codewiser\HttpCacheControl\Contracts\Cacheable;
use DateInterval;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Responsable;
use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class CacheControl implements Responsable
{
    protected int $private = 0;
    protected bool|Closure $etag;
    protected bool|Closure $lastModified;
    protected Closure $locale;
    protected DateInterval|int|null $ttl = null;

    /**
     * Make CacheControl instance using CacheInterface or Cacheable model or name of Cacheable model class.
     *
     * @param  CacheInterface|Cacheable|string  $cache
     * @param  Closure  $response
     *
     * @return static
     */
    public static function make(CacheInterface|Cacheable|string $cache, Closure $response): static
    {
        if (is_string($cache)) {
            if (class_exists($cache)) {
                $cache = new $cache;
            } else {
                throw new InvalidArgumentException(__('Class :class doesnt exist', ['class' => $cache]));
            }
        }

        if (!($cache instanceof CacheInterface)) {
            if ($cache instanceof Cacheable) {
                $cache = $cache->cache();
            } else {
                throw new InvalidArgumentException(__(':Model should implement :contract', [
                    'model' => get_class($cache),
                    'contract' => Cacheable::class
                ]));
            }
        }

        return new static($cache, $response);
    }

    public function __construct(
        protected CacheInterface $cache,
        protected Closure $response
    ) {
        // Set defaults
        $this->etag = false;
        $this->lastModified = false;
        $this->locale(fn() => app()->getLocale());
    }

    /**
     * Just for testing.
     *
     * @internal
     */
    public function locale(string|Closure $locale): static
    {
        $this->locale = is_string($locale) ? fn() => $locale : $locale;

        return $this;
    }

    public function ttl(DateInterval|int|null $ttl = null): static
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * Means that cache must not be shared across users.
     */
    public function private(?Authenticatable $user): static
    {
        if ($user) {
            $this->private = $user->getAuthIdentifier();
        }

        return $this;
    }

    /**
     * Means that cache is user independent.
     */
    public function public(): static
    {
        $this->private = 0;

        return $this;
    }

    /**
     * The If-Modified-Since request HTTP header makes the request conditional:
     * the server sends back the requested resource, with a 200 status,
     * only if it has been last modified after the given date.
     * If the resource has not been modified since, the response is a 304 without any body;
     * the Last-Modified response header of a previous request contains the date of last modification.
     * Unlike If-Unmodified-Since, If-Modified-Since can only be used with a GET or HEAD.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/If-Modified-Since
     */
    public function lastModified(Closure $closure): static
    {
        $this->lastModified = $closure;

        return $this;
    }

    /**
     * The If-None-Match HTTP request header makes the request conditional.
     * For GET and HEAD methods, the server will return the requested resource, with a 200 status,
     * only if it doesn't have an ETag matching the given ones.
     *
     * When the condition fails for GET and HEAD methods,
     * then the server must return HTTP status code 304 (Not Modified).
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/If-None-Match
     */
    public function etag(bool|Closure $closure = true): static
    {
        $this->etag = $closure;

        return $this;
    }

    protected function response($request): BaseResponse
    {
        $response = call_user_func($this->response, $request);

        if (!($response instanceof BaseResponse)) {
            $response = response()->make($response);
        }

        while ($response instanceof Responsable) {
            $response = $response->toResponse($request);
        }

        return $response;
    }

    public function toResponse($request)
    {
        $key = md5(json_encode([
            $this->private,
            $request->method(),
            $request->path(),
            $request->headers->all(),
            $request->all(),
            call_user_func($this->locale),
        ]));

        $k_etag = $key.'/etag';
        $k_last = $key.'/last_modified';

        $options = [
            'etag'          => $this->cache->get($k_etag),
            'last_modified' => $this->cache->get($k_last)
        ];

        /**
         * 1. Make empty response,
         *      set etag and last_modified headers from cache,
         *      check if response is not modified?
         */
        $response = response()->make();
        $response->setCache($options);

        if ($response->isNotModified($request)) {

            // Touch cache
            $this->cache->set($k_etag, $options['etag'], $this->ttl);
            $this->cache->set($k_last, $options['last_modified'], $this->ttl);

            return $response;
        }

        /**
         * 2. Get response with fresh data,
         *      get fresh headers values.
         */
        $response = $this->response($request);

        // Update cache with actual values
        if ($this->etag === true) {
            $options['etag'] = md5($response->getContent());
        } elseif (is_callable($this->etag)) {
            $options['etag'] = call_user_func($this->etag, $response);
        }

        if (is_callable($this->lastModified)) {
            $options['last_modified'] = call_user_func($this->lastModified);
        }

        /**
         * 3. Fill response with that headers
         *      and check if response is not modified one more time.
         */
        $response->setCache($options);
        $response->isNotModified($request);

        // Touch cache
        $this->cache->set($k_etag, $options['etag'], $this->ttl);
        $this->cache->set($k_last, $options['last_modified'], $this->ttl);

        return $response;
    }
}