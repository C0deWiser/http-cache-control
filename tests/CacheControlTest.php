<?php

namespace Codewiser\HttpCacheControl\Tests;

use Codewiser\HttpCacheControl\CacheControl;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class CacheControlTest extends TestCase
{
    protected CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new Cache();
    }

    public function testCache()
    {
        $this->assertNull($this->cache->get('foo'));
        $this->assertFalse($this->cache->has('foo'));

        $this->cache->set('foo', 'fighters');
        $this->assertEquals('fighters', $this->cache->get('foo'));
        $this->assertTrue($this->cache->has('foo'));

        $this->cache->delete('foo');
        $this->assertNull($this->cache->get('foo'));
        $this->assertFalse($this->cache->has('foo'));

        $this->cache->set('foo', 'fighters');
        $this->cache->clear();
        $this->assertNull($this->cache->get('foo'));
        $this->assertFalse($this->cache->has('foo'));

        $this->cache->set('foo', 'fighters', new \DateInterval('PT1S'));
        $this->assertTrue($this->cache->has('foo'));
        sleep(2);
        $this->assertNull($this->cache->get('foo'));
        $this->assertFalse($this->cache->has('foo'));
    }
}
