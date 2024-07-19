<?php

namespace Mvaliolahi\Blaze\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mvaliolahi\Blaze\Services\BlazeService;

class BlazeMiddleware
{
    protected BlazeService $service;

    public function __construct(BlazeService $service)
    {
        $this->service = $service;
    }

    public function handle(Request $request, Closure $next, $type = 'public')
    {
        $filename = $this->service->createIdentifierAndCacheFilename($request, $type);

        // Fallback: If cache exists and nginx does not hit, return the cache version.
        if ($this->service->existCacheFile($filename)) {
            $response = response($this->service->retrieveCacheFile($filename));
            $this->setCacheHeaders($response);
            return $response;
        }

        // generate a cache version from the current request.
        $response = $next($request);
        if ($response->isSuccessful() && $request->isMethod('get') && !$request->ajax()) {
            $this->setCacheHeaders($response);
            $this->service->createCacheFile($filename, $response->getContent());
        }

        return $response;
    }

    protected function setCacheHeaders($response)
    {
        // TODO :: can be replace with Laravel internal middleware
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
    }
}
