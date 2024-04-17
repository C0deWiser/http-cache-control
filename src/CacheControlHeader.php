<?php

namespace Codewiser\HttpCacheControl;

use Illuminate\Contracts\Support\Arrayable;

class CacheControlHeader implements Arrayable
{
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
        ]);
    }
}