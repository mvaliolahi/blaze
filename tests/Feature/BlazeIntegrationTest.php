<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\DB;

class BlazeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        // Create users table for testing
        DB::statement('
      CREATE TABLE users (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name VARCHAR(255) NOT NULL,
          email VARCHAR(255) NOT NULL,
          password VARCHAR(255) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
  ');

        // Set up routes for testing
        Route::middleware(['web', 'blaze:public'])->get('/public', function () {
            return 'public content';
        });

        Route::middleware(['web', 'blaze:private'])->get('/private', function () {
            return 'private content';
        });
    }

    public function testPublicRouteCachesResponse()
    {
        $cacheFile = public_path('cache/public__public?.html');

        // Ensure the cache file does not exist before the request
        if (File::exists($cacheFile)) {
            File::delete($cacheFile);
        }

        // Send request to the public route
        $response = $this->get('/public');

        // Assert the response is as expected
        $response->assertStatus(200);
        $response->assertSee('public content');

        // Assert the cache file has been created
        $this->assertFileExists($cacheFile);

        // Clean up the cache file
        File::delete($cacheFile);
    }

    public function testPrivateRouteCachesResponse()
    {
        // Extend User model to include fillable properties
        $user = new class extends BaseUser {
            protected $table = 'users';
            protected $fillable = ['name', 'email', 'password'];
        };

        // Create a user directly
        $user = $user->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        Auth::login($user);

        $identifier = md5(config('app.key') . $user->id);
        $cacheFile = public_path("cache/{$identifier}__private?.html");

        // Ensure the cache file does not exist before the request
        if (File::exists($cacheFile)) {
            File::delete($cacheFile);
        }

        // Send request to the private route
        $response = $this->get('/private');

        // Assert the response is as expected
        $response->assertStatus(200);
        $response->assertSee('private content');

        // Assert the cache file has been created

        $this->assertFileExists($cacheFile);

        // Clean up the cache file
        File::delete($cacheFile);
    }

    protected function tearDown(): void
    {
        // Clean up any remaining cache files
        $cacheDir = public_path('cache');
        foreach (File::files($cacheDir) as $file) {
            File::delete($file);
        }

        // Drop the users table after tests
        DB::statement('DROP TABLE IF EXISTS users');

        parent::tearDown();
    }
}
