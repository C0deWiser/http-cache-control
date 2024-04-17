<?php

namespace Codewiser\HttpCacheControl;

use Closure;
use Codewiser\HttpCacheControl\Contracts\Cacheable;
use DateInterval;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class CacheControl implements Responsable
{
    /**
     * @deprecated
     */
    protected int $private = 0;
    protected bool|Closure $etag;
    protected bool|Closure $lastModified;
    protected bool $content;
    /**
     * @deprecated
     */
    protected Closure $locale;
    protected DateInterval|int|null $ttl = null;
    protected array $options = [];
    protected ?\DateTimeInterface $expires = null;
    protected ?array $vary = null;

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

        if ($cache instanceof Cacheable) {
            $cache = $cache->cache();
        }

        if ($cache instanceof CacheInterface) {
            return new static($cache, $response);
        }

        throw new InvalidArgumentException(__(':Model should implement :contract', [
            'model'    => get_class($cache),
            'contract' => Cacheable::class
        ]));
    }

    public function __construct(
        protected CacheInterface $cache,
        protected Closure $response
    ) {
        // Set defaults
        $this->etag = false;
        $this->content = false;
        $this->lastModified = false;
        $this->locale(fn() => app()->getLocale());
    }

    /**
     * Just for testing.
     *
     * @internal
     * @deprecated
     */
    public function locale(string|Closure $locale): static
    {
        $this->locale = is_string($locale) ? fn() => $locale : $locale;

        return $this;
    }

    /**
     * Set cache time-to-live.
     */
    public function ttl(DateInterval|int|null $ttl = null): static
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * Set Cache-Control response header value.
     *
     * @deprecated
     */
    public function options(array|Arrayable $options): static
    {
        return $this->cacheControl($options);
    }

    /**
     * Set Cache-Control response header value.
     */
    public function cacheControl(array|Arrayable $options): static
    {
        if ($options instanceof Arrayable) {
            $options = $options->toArray();
        }

        $this->options = $options;

        return $this;
    }

    /**
     * Add Expires response header.
     */
    public function expires(\DateTimeInterface $expires): static
    {
        $this->expires = $expires;

        return $this;
    }

    /**
     * Add Vary response headers. Cache is depended on those request headers.
     */
    public function vary($headers): static
    {
        $this->vary = is_array($headers) ? $headers : func_get_args();

        return $this;
    }

    /**
     * Means that cache must not be shared across users.
     *
     * @deprecated
     */
    public function private(?Authenticatable $user): static
    {
        if ($user) {
            $this->private = $user->getAuthIdentifier();
            $this->options['private'] = true;

            if (isset($this->options['public'])) {
                unset($this->options['public']);
            }
        }

        return $this;
    }

    /**
     * Means that cache is user independent.
     *
     * @deprecated
     */
    public function public(): static
    {
        $this->private = 0;

        $this->options['public'] = true;

        if (isset($this->options['private'])) {
            unset($this->options['private']);
        }

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
     *
     * @deprecated
     */
    public function ifModifiedSince(Closure $closure): static
    {
        return $this->lastModified($closure);
    }

    /**
     * Add Last-Modified response header.
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
     * @deprecated
     */
    public function ifNoneMatch(bool|Closure $closure = true): static
    {
        return $this->etag($closure);
    }

    /**
     * Add ETag response header. If no closure provided, the ETag will be calculated from response content.
     */
    public function etag(bool|Closure $closure = true): static
    {
        $this->etag = $closure;

        return $this;
    }

    /**
     * Cache and reuse entire response content.
     * @deprecated
     */
    public function content(bool $content = true): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Cache and reuse entire response content.
     */
    public function remember(bool $content = true): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Execute closure to get response content. Then make a response object.
     */
    protected function response($request, ?string $content): BaseResponse
    {
        $response = $content ?? call_user_func($this->response, $request);

        while ($response instanceof Responsable) {
            $response = $response->toResponse($request);
        }

        if (!($response instanceof BaseResponse)) {
            $response = response()->make($response);
        }

        return $response;
    }

    /**
     * Get cache key prefix.
     */
    protected function prefix(Request $request): string
    {
        $vary = $this->vary ?? [];

        // Cache depends on request headers from Vary headers list.
        $headers = array_filter(
            $request->headers->all(),
            function ($requestHeaderName) use ($vary) {
                foreach ($vary as $varyHeaderName) {
                    if (strtoupper($requestHeaderName) === strtoupper($varyHeaderName)) {
                        return true;
                    }
                }
                return false;
            },
            ARRAY_FILTER_USE_KEY
        );

        // Private option means that cache is user dependent.
        if ($this->options['private'] ?? null || !($this->options['public'] ?? false)) {
            $user = $request->user()?->getAuthIdentifier();
        } else {
            $user = null;
        }

        // Cache depends on:
        return md5(json_encode([
            // method
            $request->method(),
            // path
            $request->path(),
            // query params
            $request->all(),
            // maybe user key
            $user,
            // vary headers with their values
            $headers
        ]));
    }

    public function toResponse($request)
    {
        $key = $this->prefix($request);

        // Values we may cache
        $k_etag = $key.'/etag';
        $k_last = $key.'/last_modified';
        $k_page = $key.'/content';

        // Read cached values
        $content = $this->cache->get($k_page);
        $options = $this->options;
        if ($this->etag) {
            $options['etag'] = $this->cache->get($k_etag);
        }
        if ($this->lastModified) {
            $options['last_modified'] = $this->cache->get($k_last);
        }

        // Function to update cache with callbacks
        $touch = function (array $options, ?string $content) use ($k_page, $k_etag, $k_last) {
            if ($this->content) {
                // We should cache entire content
                $this->cache->set($k_page, $content, $this->ttl);
            }
            if ($this->etag) {
                // We should cache etag value
                $this->cache->set($k_etag, $options['etag'], $this->ttl);
            }
            if ($this->lastModified) {
                // We should cache last_modified value
                $this->cache->set($k_last, $options['last_modified'], $this->ttl);
            }
        };

        /**
         * 1. Make empty response,
         *      set etag and last_modified headers from cache,
         *      check if response is not modified?
         */
        $response = response()->make()->setCache($options);

        if ($response->isNotModified($request)) {
            // Touch cache
            $touch($options, $content);
            // Response with 304 Not Modified
            return $response;
        }

        /**
         * 2. Get response with fresh data,
         *      get fresh headers values.
         */
        $response = $this->response($request,
            // Reuse cached content. If no content was cached, the callback for fresh content will be called.
            $this->content ? $content : null
        );

        // Update variables with actual values
        $content = $response->getContent();

        if ($this->etag === true) {
            // Implicit etag
            $options['etag'] = md5($content);
        } elseif (is_callable($this->etag)) {
            // Explicit etag
            $options['etag'] = call_user_func($this->etag, $response);
        }

        if (is_callable($this->lastModified)) {
            // Explicit last_modified
            $last_modified = call_user_func($this->lastModified);
            if (is_int($last_modified)) {
                $last_modified = Carbon::createFromTimestamp($last_modified);
            }
            if ($last_modified instanceof \DateTimeInterface) {
                $options['last_modified'] = $last_modified;
            } else {
                throw new InvalidArgumentException('Last-Modified must be a timestamp or an instance of DateTimeInterface');
            }
        }

        /**
         * 3. Fill response with that headers
         *      and check if response is not modified one more time.
         */
        if ($this->vary) {
            $response->setVary(implode(', ', $this->vary));
        }
        if ($this->expires) {
            $response->setExpires($this->expires);
        }
        $response
            ->setCache($options)
            ->isNotModified($request);

        // Touch cache
        $touch($options, $content);

        return $response;
    }
}