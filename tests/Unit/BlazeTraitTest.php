<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;
use Mvaliolahi\Blaze\Services\BlazeService;
use Mvaliolahi\Blaze\Traits\Blaze;

class BlazeTraitTest extends TestCase
{
    use Blaze;

    public function setUp(): void
    {
        parent::setUp();

        // Define routes used in the tests
        Route::get('/posts', function () {})->name('posts');
        Route::get('/profile', function () {})->name('profile');
    }

    public function testInvalidateCache()
    {
        $service = Mockery::mock(BlazeService::class);
        $this->app->instance(BlazeService::class, $service);

        $service->shouldReceive('invalidatePublicRoute')
                ->with('/posts', true, null)
                ->once();

        $service->invalidatePublicRoute('/posts', true, null);
    }
}
