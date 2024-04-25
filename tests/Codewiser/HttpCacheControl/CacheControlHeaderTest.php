<?php

namespace Codewiser\HttpCacheControl;

use PHPUnit\Framework\TestCase;

class CacheControlHeaderTest extends TestCase
{
    public function test()
    {
        $h = new CacheControlHeader(
            max_age: 0
        );

        $this->assertEquals(['max_age' => 0], $h->toArray());
    }
}
