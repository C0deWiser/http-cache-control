<?php

namespace Codewiser\HttpCacheControl;

use Closure;
use Codewiser\HttpCacheControl\Contracts\CacheControlled;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Examines Cache-Control headers and builds proper Response.
 */
class HttpCacheControl implements Responsable
{
    protected CacheControlled $model;

    protected Closure $response;

    protected ResponseHeaderBag $headers;

    protected ?TaggedCache $cache = null;

    /**
     * @var Closure|bool|null
     */
    protected $etagResolver = null;
    protected ?Closure $lastModifiedResolver = null;

    protected ?DateTimeInterface $expire = null;
    protected array $tags = [];

    /**
     * @var mixed
     */
    protected $private = null;

    /**
     * Construct with Class.
     */
    public static function index(string $class): HttpCacheControl
    {
        return new static(new $class);
    }

    /**
     * Construct with Model.
     */
    public static function show(CacheControlled $model): HttpCacheControl
    {
        return new static($model);
    }

    public function __construct(CacheControlled $model)
    {
        $this->model = $model;
    }

    /**
     * Add tags to the cache.
     */
    public function dependsOn($tags): self
    {
        $this->model->cache()->dependsOn($tags);

        return $this;
    }

    /**
     * Modify cache expiration timeout.
     */
    public function setCacheExpire(DateTimeInterface $expire): self
    {
        $this->expire = $expire;

        if ($this->cache) {
            $this->cache->setExpire($this->expire);
        }

        return $this;
    }

    /**
     * Get cache driver.
     */
    public function cache(): TaggedCache
    {
        if (!$this->cache) {
            $this->cache = clone $this->model->cache();

            $this->cache->prependTag('request');

            if ($this->expire) {
                $this->cache->setExpire($this->expire);
            }
        }

        return $this->cache;
    }

    /**
     * Means that cache must not be shared across users.
     */
    public function setPrivate(?Authenticatable $user): self
    {
        if ($user) {
            $this->private = $user;
        }

        return $this;
    }

    /**
     * Means that cache is user independent.
     */
    public function setPublic(): self
    {
        $this->private = 0;

        return $this;
    }

    public function setResponse(Closure $response): self
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @param Request $request
     * @return Responsable|BaseResponse
     */
    protected function getResponse(Request $request)
    {
        return call_user_func($this->response, $request);
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
    public function setLastModified(Closure $lastModifiedResolver): self
    {
        $this->lastModifiedResolver = $lastModifiedResolver;

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
    public function setEtag(Closure $etagResolver): self
    {
        $this->etagResolver = $etagResolver;

        return $this;
    }

    /**
     * Append ETag to the response, based on response content.
     */
    public function withEtag(): self
    {
        $this->etagResolver = true;

        return $this;
    }

    public function toResponse($request)
    {
        $cache = $this->cache()
            // Isolate cache keys with request signature
            ->withPrefix([
                $this->private,
                strtolower($request->method()),
                $request->path(),
                md5(json_encode($request->all())),
                app()->getLocale(),
            ]);

        $etagKey = 'etag';
        $lastModifiedKey = 'last_modified';

        $options = [
            'etag' => $cache->get($etagKey),
            'last_modified' => $cache->get($lastModifiedKey)
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
            $cache->put($etagKey, $options['etag']);
            $cache->put($lastModifiedKey, $options['last_modified']);

            return $response;
        }

        /**
         * 2. Get response with fresh data,
         *      get fresh headers values.
         */
        $response = $this->getResponse($request);
        if ($response instanceof Responsable) {
            $response = $response->toResponse($request);
        }
        // Update cache with actual values
        if ($this->etagResolver === true) {
            $options['etag'] = md5($response->getContent());
        }
        if (is_callable($this->etagResolver)) {
            $options['etag'] = call_user_func($this->etagResolver, $response);
        }
        if (is_callable($this->lastModifiedResolver)) {
            $options['last_modified'] = call_user_func($this->lastModifiedResolver);
        }

        /**
         * 3. Fill response with that headers
         *      and check if response is not modified one more time.
         */
        $response->setCache($options);
        $response->isNotModified($request);

        // Touch cache
        $cache->put($etagKey, $options['etag']);
        $cache->put($lastModifiedKey, $options['last_modified']);

        return $response;
    }

}
