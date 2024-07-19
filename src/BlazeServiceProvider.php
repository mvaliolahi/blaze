<?php

namespace Mvaliolahi\Blaze;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mvaliolahi\Blaze\Commands\ClearBlazeCache;
use Mvaliolahi\Blaze\Middleware\BlazeMiddleware;
use Mvaliolahi\Blaze\Services\BlazeService;

class BlazeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(BlazeService::class, function ($app) {
            return new BlazeService();
        });
    }

    public function boot(Router $router)
    {
        $router->aliasMiddleware('blaze', BlazeMiddleware::class);
        $this->blazeCSRF();
        $this->commands([
            ClearBlazeCache::class,
        ]);
    }

    private function blazeCSRF()
    {
        // Register the blaze/csrf-token route
        Route::middleware('web')->group(function () {
            Route::get('blaze/csrf-token', function () {
                return response()->json(['token' => csrf_token()]);
            })->name('blaze.csrf');
        });

        // JS logic for update meta tag csrf token!
        Blade::directive('blaze', function () {
            return <<<SCRIPT
            <script>
                function updateCsrfToken() {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', '/blaze/csrf-token', true);

                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === XMLHttpRequest.DONE) {
                            if (xhr.status === 200) {
                                var csrfToken = JSON.parse(xhr.responseText).token;
                                console.log(csrfToken)

                                // Update the meta tag
                                var metaTag = document.querySelector('meta[name="csrf-token"]');
                                if (metaTag) {
                                    metaTag.setAttribute('content', csrfToken);
                                } else {
                                    console.error('CSRF meta tag not found.');
                                }

                                // Update all hidden input fields with the name _token
                                var tokenInputs = document.querySelectorAll('input[name="_token"]');
                                tokenInputs.forEach(function(input) {
                                    input.value = csrfToken;
                                console.log(csrfToken)

                                });
                            } else {
                                console.error('Error fetching CSRF token:', xhr.status);
                            }
                        }
                    };

                    xhr.send();
                }

                // Update CSRF token on page load
                updateCsrfToken();
            </script>
            SCRIPT;
        });
    }
}
