<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;
use Mvaliolahi\Blaze\Services\BlazeService;
use Mvaliolahi\Blaze\Middleware\BlazeMiddleware;
use Mockery;

class BlazeMiddlewareTest extends TestCase
{
    protected $service;
    protected $middleware;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = Mockery::mock(BlazeService::class);
        $this->middleware = new BlazeMiddleware($this->service);
    }

    public function testHandleCacheHit()
    {
        $request = Request::create('/posts', 'GET');
        $filename = 'cache/public_posts.html';

        $this->service->shouldReceive('createIdentifierAndCacheFilename')
                      ->with($request, 'public')
                      ->andReturn($filename);

        $this->service->shouldReceive('existCacheFile')
                      ->with($filename)
                      ->andReturn(true);

        $this->service->shouldReceive('retrieveCacheFile')
                      ->with($filename)
                      ->andReturn('<html>cached content</html>');

        $next = function ($req) {
            return new Response('dynamic content');
        };

        $response = $this->middleware->handle($request, $next, 'public');

        $this->assertEquals('<html>cached content</html>', $response->getContent());
    }

    public function testHandleCacheMiss()
    {
        $request = Request::create('/posts', 'GET');

        // Create a response to be returned by the next middleware
        $responseContent = '<html>Posts</html>';
        $response = response($responseContent, 200);

        // Create a partial mock of the BlazeService
        $service = Mockery::mock(BlazeService::class)->makePartial();
        $service->shouldReceive('createIdentifierAndCacheFilename')
                ->with($request, 'public')
                ->andReturn('cache/public__posts.html');
        $service->shouldReceive('existCacheFile')
                ->with('cache/public__posts.html')
                ->andReturn(false);
        $service->shouldReceive('createCacheFile')
                ->with('cache/public__posts.html', $responseContent)
                ->once();

        // Create the middleware instance
        $middleware = new BlazeMiddleware($service);

        // Set up a pipeline with the next middleware returning the response
        $next = function ($req) use ($response) {
            return $response;
        };

        // Call the middleware handle method
        $result = $middleware->handle($request, $next, 'public');

        // Assert the response is the same as the one returned by the next middleware
        $this->assertEquals($responseContent, $result->getContent());
        $this->assertEquals(200, $result->getStatusCode());
    }
}
