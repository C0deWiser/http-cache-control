<?php


namespace Codewiser\HttpCacheControl;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Tagged cache helper
 * @deprecated
 */
class TaggedCache
{
    protected string $prefix = '';
    protected array $tags = [];
    protected DateTimeInterface $expire;

    /**
     * Construct cache with tag.
     */
    public static function for(string $subject, ?DateTimeInterface $expire = null): TaggedCache
    {
        return new static($subject, $expire);
    }

    public function __construct(string $subject, ?DateTimeInterface $expire = null)
    {
        $this->dependsOn($subject);

        $this->setExpire($expire ?? now()->addWeek());
    }

    /**
     * Isolate cache with given arguments.
     *
     * @param string|array $arguments
     */
    public function withPrefix($arguments): self
    {
        $prefix = [];

        foreach ((array)$arguments as $argument) {
            if ($argument instanceof Model) {
                $class = get_class($argument);
                $prefix[] = (defined("$class::tag") ? $class::tag : $class) . '#' . $argument->getKey();
            }
            if (is_scalar($argument)) {
                $prefix[] = $argument;
            }
        }

        $prefix = array_filter($prefix);
        $prefix = implode('/', $prefix);

        $this->prefix = $prefix ? $prefix . '/' : '';

        return $this;
    }

    /**
     * Set cache expiration time.
     */
    public function setExpire(DateTimeInterface $expire): self
    {
        $this->expire = $expire;

        return $this;
    }

    /**
     * Add tags to the cache.
     */
    public function dependsOn($tags): self
    {
        $tags = is_array($tags) ? $tags : func_get_args();

        $this->tags = array_merge($this->tags, $tags);

        $this->tags = array_unique($this->tags);

        return $this;
    }

    /**
     * Set tag at first position.
     */
    public function prependTag($tag): self
    {
        array_unshift($this->tags, $tag);

        return $this;
    }

    /**
     * Get name for variable in cache.
     */
    public function cachingKey($id): string
    {
        return $this->tags[0] . '/' . $this->prefix . $id;
    }

    /**
     * Get Laravel Cache driver.
     */
    public function driver(): \Illuminate\Cache\TaggedCache
    {
        return Cache::tags($this->tags);
    }

    /**
     * Remove all items from the cache.
     */
    public function invalidate(): void
    {
        try {
            $this->driver()->flush();
        } catch (BindingResolutionException $e) {

        }
    }

    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key)
    {
        return $this->driver()->get($this->cachingKey($key));
    }

    /**
     * Store an item in the cache.
     */
    public function put(string $key, $value): bool
    {
        return $this->driver()->put($this->cachingKey($key), $value, $this->expire);
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     */
    public function remember(string $key, Closure $callback)
    {
        return $this->driver()
            ->remember($this->cachingKey($key), $this->expire, function () use ($callback, $key) {
                return $callback($key);
            });
    }

    /**
     * Remove an item from the cache.
     */
    public function forget(string $key): bool
    {
        return $this->driver()
            ->forget($this->cachingKey($key));
    }
}
