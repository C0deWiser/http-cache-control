<?php

namespace Codewiser\HttpCacheControl\Tests;

use DateInterval;
use DateTime;
use Psr\SimpleCache\CacheInterface;

class Cache implements CacheInterface
{
    protected array $storage = [];
    /**
     * @var array<DateTime>
     */
    protected array $expires = [];

    protected function setExpire(string $key, DateInterval|int|null $ttl = null): void
    {
        if ($ttl) {
            $now = new DateTime;

            if (is_int($ttl)) {
                $ttl = new DateInterval("PT{$ttl}S");
            }

            $this->expires[$key] = $now->add($ttl);
        } elseif (isset($this->expires[$key])) {
            unset($this->expires[$key]);
        }
    }

    protected function expire(string $key): void
    {
        if (isset($this->expires[$key])) {
            $expire = $this->expires[$key];
            $diff = $expire->diff(new DateTime);

            if (!$diff->invert) {
                $this->delete($key);
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->expire($key);
        return $this->storage[$key] ?? $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->setExpire($key, $ttl);
        $this->storage[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);
        }
        if (isset($this->expires[$key])) {
            unset($this->expires[$key]);
        }

        return true;
    }

    public function clear(): bool
    {
        $this->storage = [];
        $this->expires = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];

        foreach ($keys as $key) {
            $values[] = $this->get($key, $default);
        }

        return $values;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $this->expire($key);
        return isset($this->storage[$key]);
    }
}