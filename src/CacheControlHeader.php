<?php

namespace Codewiser\HttpCacheControl;

use Illuminate\Contracts\Support\Arrayable;

class CacheControlHeader implements Arrayable
{
    /**
     * @param  \DateTimeInterface|\DateInterval|int|null  $max_age  indicates that the response remains fresh until N seconds after the response is generated.
     * @param  \DateTimeInterface|\DateInterval|int|null  $s_maxage  indicates how long the response remains fresh in a shared cache.
     * @param  \DateTimeInterface|\DateInterval|int|null  $stale_while_revalidate indicates that the cache could reuse a stale response while it revalidates it to a cache.
     * @param  \DateTimeInterface|\DateInterval|int|null  $stale_if_error indicates that the cache can reuse a stale response when an upstream server generates an error, or when the error is generated locally.
     * @param  bool|null  $public  indicates that the response can be stored in a shared cache.
     * @param  bool|null  $private  indicates that the response can be stored only in a private cache (e.g. local caches in browsers).
     * @param  bool|null  $must_revalidate  indicates that the response can be stored in caches and can be reused while fresh.
     * @param  bool|null  $no_cache  indicates that the response can be stored in caches, but must be validated before each reuse.
     * @param  bool|null  $no_store  indicates that any caches of any kind (private or shared) should not store this response.
     * @param  bool|null  $no_transform indicates that any intermediary (regardless of whether it implements a cache) shouldn't transform the response contents.
     * @param  bool|null  $proxy_revalidate  equivalent of must-revalidate, but specifically for shared caches only.
     * @param  bool|null  $immutable indicates that the response will not be updated while it's fresh.
     * @param  bool|null  $must_understand  indicates that a cache should store the response only if it understands the requirements for caching based on status code.
     */
    public function __construct(
        public \DateTimeInterface|\DateInterval|int|null $max_age = null,
        public \DateTimeInterface|\DateInterval|int|null $s_maxage = null,
        public \DateTimeInterface|\DateInterval|int|null $stale_while_revalidate = null,
        public \DateTimeInterface|\DateInterval|int|null $stale_if_error = null,
        public ?bool $public = null,
        public ?bool $private = null,
        public ?bool $must_revalidate = null,
        public ?bool $no_cache = null,
        public ?bool $no_store = null,
        public ?bool $no_transform = null,
        public ?bool $proxy_revalidate = null,
        public ?bool $immutable = null,
        public ?bool $must_understand = null,
    ) {
        //
    }

    protected function scalar(\DateTimeInterface|\DateInterval|int|null $value = null): ?int
    {
        if ($value instanceof \DateInterval) {
            $value = (new \DateTime())->add($value);
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->getTimestamp() - time();
        }

        return $value;
    }

    public function toArray(): array
    {
        return array_filter([
            'max_age'                => $this->scalar($this->max_age),
            's_maxage'               => $this->scalar($this->s_maxage),
            'stale_while_revalidate' => $this->scalar($this->stale_while_revalidate),
            'stale_if_error'         => $this->scalar($this->stale_if_error),
            'public'                 => $this->public,
            'private'                => $this->private,
            'must_revalidate'        => $this->must_revalidate,
            'no_cache'               => $this->no_cache,
            'no_store'               => $this->no_store,
            'no_transform'           => $this->no_transform,
            'proxy_revalidate'       => $this->proxy_revalidate,
            'immutable'              => $this->immutable,
            'must_understand'        => $this->must_understand,
        ], fn($value) => !is_null($value));
    }
}