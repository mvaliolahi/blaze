<?php

namespace Mvaliolahi\Blaze\Services;

use Fiber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Mvaliolahi\Blaze\Traits\FileUtils;
use Str;

class BlazeService
{
    use FileUtils;

    public function createIdentifierAndCacheFilename(Request $request, string $type):string
    {
        // 1. create cache identifier
        $identifier = $this->createIdentifierUsingType($type, $request?->user()?->id);
        $this->saveIdentifierInCookie($request, $type);
        // 2. create cache directory if does not exists
        $this->createCacheDirectoryIfDoesNotExists($this->cacheDirectory());
        // 3. create a clean file name
        $cacheKey = $this->sanitizeUri($request->getRequestUri());

        return "{$this->cacheDirectory()}/{$identifier}_{$cacheKey}?.html";
    }

    public function invalidatePublicRoute($route, bool $refresh = false, ?string $baseUrl)
    {
        $this->invalidateCacheForPattern('public', $this->convertRouteToCacheFilename($route));
        $this->refreshCache(refresh:$refresh, route: $route, baseUrl: $baseUrl);
    }

    public function invalidatePrivateRoute(string $route, bool $refresh = false, ?string $baseUrl)
    {
        // Using Auth::id() can bring a bug when identifier generates using uuid,
        // We must check if user is not login read identifier from cookie.
        if (Auth::check()) {
            $identifier = $this->createIdentifierUsingType("private", Auth::id());
        } else {
            $identifier = Cookie::get('blaze_id');
        }
        $this->invalidateCacheForPattern($identifier, $this->convertRouteToCacheFilename($route));
        $this->refreshCache(refresh:$refresh, route: $route, baseUrl:$baseUrl);
    }

    private function convertRouteToCacheFilename($route)
    {
        $route = route($route);
        // Remove base url from route, for generate file name
        $route = str_replace(config('app.url'), "", $route);
        return $this->sanitizeUri($route);
    }

    public function invalidateCacheForPattern(string $identifier, string $route):void
    {
        $filenamePattern = public_path("cache/{$identifier}_{$route}*.html");

        foreach (glob($filenamePattern) as $cacheFile) {
            if (File::exists($cacheFile)) {
                File::delete($cacheFile);
            }
        }
    }

    public function refreshCache(bool $refresh, $route, ?string $baseUrl)
    {
        if (!$refresh) {
            return;
        }

        $url = route($route);

        // This block of code is useful when the application run is docker, specially in development.
        if ($baseUrl) {
            $url = str_replace(config('app.url'), $baseUrl, $url);
        }

        try {
            $fiber = new Fiber(function() use ($url) {
                $headers = ['Cookie' => $this->generate_auth_cookie()];
                return Http::withHeaders($headers ?? [])->get($url);
            });
            $fiber->start();

        } catch (\Exception $e) {
            Log::error($e->getMessage());
        } finally {
        }
    }

    public function sanitizeUri($uri)
    {
        return str_replace(['/'], '_', $uri);
    }

    private function saveIdentifierInCookie(Request $request):void
    {
        // Set a cookie for cache identifier for use inside webserver for retrieve cache.
        // the blade_id must be added to EncryptCookies::class, $expect
        // use the $user->id otherwise uuid()
        if (!Cookie::has('blaze_id')) {
            $identifier = $this->createIdentifierUsingType('private', $request?->user()?->id ?? Str::uuid());
            Cookie::queue(Cookie::forever('blaze_id', $identifier, null, null, false, true, true, 'lax'));
        }
    }

    public function createIdentifierUsingType(string $type, $userId = null): string
    {
        return $type === 'private' && $userId
        ? md5(config('app.key') . $userId)
        : 'public';
    }

    private function cacheDirectory()
    {
        return public_path('cache');
    }

    public function generate_auth_cookie(): string
    {
        $sessionName = Session::getName();
        $encrypter = app(\Illuminate\Encryption\Encrypter::class);

        $encrypted =  $encrypter->encrypt(
            \Illuminate\Cookie\CookieValuePrefix::create($sessionName, $encrypter->getKey()).Session::getId(),
            false
        );

        return trim("{$sessionName}={$encrypted}");
    }
}
