<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Mvaliolahi\Blaze\Services\BlazeService;
use Mockery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class BlazeServiceTest extends TestCase
{
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new BlazeService();

        // Define routes used in the tests
        Route::get('/posts', function () {})->name('posts');
        Route::get('/profile', function () {})->name('profile');
    }

    public function testCreateIdentifierAndCacheFilename()
    {
        $request = Request::create('/posts', 'GET');
        $filename = $this->service->createIdentifierAndCacheFilename($request, 'public');
        $this->assertStringContainsString('public__posts', $filename);
    }

    public function testInvalidatePublicRoute()
    {
        $route = 'posts';
        $service = Mockery::mock(BlazeService::class)->makePartial();
        $service->shouldReceive('invalidateCacheForPattern')
                ->with('public', '_posts')
                ->once();
        $service->shouldReceive('refreshCache')
                ->with(true, $route, null)
                ->once();

        $service->invalidatePublicRoute(route: $route, refresh: true, baseUrl: null);
    }

    public function testInvalidatePrivateRoute()
    {
        $route = 'profile';
        $userId = 1;

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($userId);
        $service = Mockery::mock(BlazeService::class)->makePartial();
        $identifier = md5(config('app.key') . $userId);

        $service->shouldReceive('invalidateCacheForPattern')
                ->with($identifier, '_profile')
                ->once();
        $service->shouldReceive('refreshCache')
                ->with(true, $route, null)
                ->once();

        $service->invalidatePrivateRoute(route: $route, refresh: true, baseUrl: null);
    }

    public function testSanitizeUri()
    {
        $uri = '/posts/1';
        $sanitized = $this->service->sanitizeUri($uri);
        $this->assertEquals('_posts_1', $sanitized);
    }

    public function testRefreshCache()
    {
        $route = 'posts';
        $url = route($route);
        $service = Mockery::mock(BlazeService::class)->makePartial();

        Http::shouldReceive('withHeaders')
            ->once()
            ->andReturnSelf();
        Http::shouldReceive('get')
            ->once()
            ->andReturn((object)['status' => 200]);
        $service->refreshCache(refresh: true, route: $route, baseUrl: null);
    }

    public function testGenerateAuthCookie()
    {
        Session::shouldReceive('getName')->andReturn('session_name');
        Session::shouldReceive('getId')->andReturn('session_id');
        $encrypter = Mockery::mock(\Illuminate\Encryption\Encrypter::class);
        $encrypter->shouldReceive('encrypt')
                  ->andReturn('encrypted_cookie_value');
        $encrypter->shouldReceive('getKey')
                  ->andReturn('encryption_key');
        app()->instance(\Illuminate\Encryption\Encrypter::class, $encrypter);

        $cookie = $this->service->generate_auth_cookie();
        $this->assertEquals('session_name=encrypted_cookie_value', $cookie);
    }
}

