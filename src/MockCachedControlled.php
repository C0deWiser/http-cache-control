<?php

namespace Codewiser\HttpCacheControl;


use Codewiser\HttpCacheControl\Contracts\CacheControlled;

/**
 * @deprecated
 */
class MockCachedControlled implements CacheControlled
{
    protected string $etag;
    protected \DateTime $last_modified;

    public function __construct(string $etag, \DateTime $last_modified)
    {
        $this->setEtag($etag);
        $this->setLastModified($last_modified);
    }

    public function cache(): TaggedCache
    {
        return TaggedCache::for('mock');
    }

    public function ETag(): string
    {
        return $this->etag;
    }

    public function lastModified(): \DateTimeInterface
    {
        return $this->last_modified;
    }

    /**
     * @param \DateTime $last_modified
     */
    public function setLastModified(\DateTime $last_modified): void
    {
        $this->last_modified = $last_modified;
    }

    /**
     * @param string $etag
     */
    public function setEtag(string $etag): void
    {
        $this->etag = $etag;
    }
}
