<?php

namespace Codewiser\HttpCacheControl\Tests;


use Codewiser\HttpCacheControl\HttpCacheControl;
use Codewiser\HttpCacheControl\MockCachedControlled;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

class CacheControlledTest extends TestCase
{
    protected \Faker\Generator $faker;
    protected function setUp(): void
    {
        $this->faker = Factory::create();

        parent::setUp();
    }

    protected function formatDateTime(\DateTimeInterface $dateTime): string
    {
        return $dateTime
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('D, d M Y H:i:s') . ' GMT';
    }

    protected function formatETag(string $etag): string
    {
        return '"' . $etag . '"';
    }

    public function testLastModified()
    {
        $etag = $this->faker->uuid;
        $lastModified = $this->faker->dateTime;
        $mock = new MockCachedControlled($etag, $lastModified);

        $response = HttpCacheControl::show($mock)
            ->setResponse(fn() => response()->json(['status' => 'ok']))
            ->setLastModified(fn() => $mock->lastModified())
            ->toResponse(request());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Last-Modified'));
    }

    public function testIfModifiedSinceNotModified()
    {
        $etag = $this->faker->uuid;
        $lastModified = $this->faker->dateTime;
        $mock = new MockCachedControlled($etag, $lastModified);

        // First call to init cache
        $request = request();
        $response = HttpCacheControl::show($mock)
            ->setResponse(fn() => response()->json(['status' => 'ok']))
            ->setLastModified(fn() => $mock->lastModified())
            ->toResponse($request);

        // Second call with header
        $request->headers->set('If-Modified-Since', $response->headers->get('Last-Modified'));
        $response = HttpCacheControl::show($mock)
            ->setResponse(fn() => response()->json(['status' => 'ok']))
            ->setLastModified(fn() => $mock->lastModified())
            ->toResponse($request);
        $this->assertEquals(304, $response->getStatusCode());
    }

    public function testIfModifiedSinceModified()
    {
        $etag = $this->faker->uuid;
        $lastModified = $this->faker->dateTime();
        $mock = new MockCachedControlled($etag, $lastModified);

        // First call to init cache
        $request = request();
        $response = HttpCacheControl::show($mock)
            ->setResponse(fn() => response()->json(['status' => 'ok']))
            ->setLastModified(fn() => $mock->lastModified())
            ->toResponse($request);

        $mock->cache()->invalidate();
        $mock->setLastModified($lastModified->add(new \DateInterval('P1D')));

        // Second call with header
        $request->headers->set('If-Modified-Since', $response->headers->get('Last-Modified'));
        $response = HttpCacheControl::show($mock)
            ->setResponse(fn() => response()->json(['status' => 'ok']))
            ->setLastModified(fn() => $mock->lastModified())
            ->toResponse($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testEtag()
    {
        $etag = $this->faker->uuid;
        $lastModified = $this->faker->dateTime;
        $mock = new MockCachedControlled($etag, $lastModified);

        $response = HttpCacheControl::show($mock)
            ->setResponse(fn() => response()->json(['status' => 'ok']))
            ->withEtag(fn() => $mock->ETag())
            ->toResponse(request());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Etag'));
    }

    public function testIfNoneMatchNotModified()
    {
        $etag = $this->faker->uuid;
        $lastModified = $this->faker->dateTime;
        $mock = new MockCachedControlled($etag, $lastModified);

        // First call to init cache
        $request = request();
        $response = HttpCacheControl::show($mock)
            ->setResponse(fn() => response()->json(['status' => 'ok']))
            ->withEtag()
            ->toResponse($request);

        // Second call with header
        $request->headers->set('If-None-Match', $response->headers->get('Etag'));
        $response = HttpCacheControl::show($mock)
            ->setResponse(fn() => response()->json(['status' => 'ok']))
            ->withEtag()
            ->toResponse($request);
        $this->assertEquals(304, $response->getStatusCode());
    }

    public function testIfNoneMatchModified()
    {
        $etag = $this->faker->uuid();
        $lastModified = $this->faker->dateTime;
        $mock = new MockCachedControlled($etag, $lastModified);

        // First call to init cache
        $request = request();
        $response = HttpCacheControl::show($mock)
            ->setResponse(fn() => response()->json(['status' => 'ok']))
            ->withEtag()
            ->toResponse($request);

        $mock->cache()->invalidate();

        // Second call with header
        $request->headers->set('If-None-Match', $response->headers->get('Etag'));
        $response = HttpCacheControl::show($mock)
            ->setResponse(fn() => response()->json(['status' => 'not ok']))
            ->withEtag()
            ->toResponse($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
