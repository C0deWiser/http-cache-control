<?php

namespace Codewiser\HttpCacheControl\Observers;

use Codewiser\HttpCacheControl\Contracts\Cacheable;

class InvalidatesCache
{
    /**
     * Handle the Cacheable "created" event.
     */
    public function created(Cacheable $cacheable): void
    {
        $cacheable->cache()->clear();
    }

    /**
     * Handle the Cacheable "updated" event.
     */
    public function updated(Cacheable $cacheable): void
    {
        $cacheable->cache()->clear();
    }

    /**
     * Handle the Cacheable "deleted" event.
     */
    public function deleted(Cacheable $cacheable): void
    {
        $cacheable->cache()->clear();
    }

    /**
     * Handle the Cacheable "restored" event.
     */
    public function restored(Cacheable $cacheable): void
    {
        $cacheable->cache()->clear();
    }

    /**
     * Handle the Cacheable "force deleted" event.
     */
    public function forceDeleted(Cacheable $cacheable): void
    {
        $cacheable->cache()->clear();
    }
}
