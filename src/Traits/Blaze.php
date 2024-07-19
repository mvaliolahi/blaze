<?php

namespace Mvaliolahi\Blaze\Traits;

use Mvaliolahi\Blaze\Services\BlazeService;

trait Blaze
{
    public static function bootBlaze()
    {
        static::saved(function ($model) {
            $model->invalidateCache();
        });

        static::deleted(function ($model) {
            $model->invalidateCache();
        });
    }

    public function invalidateCache()
    {
        if (!property_exists($this, 'blaze')) {
            return;
        }

        $service = app(BlazeService::class);
        $refresh =  $this->blaze_refresh ?? false;

        // TODO:: if $blaze does not set in Model, we must get routes from app router
        foreach ($this->blaze['public'] as $route) {
            $service->invalidatePublicRoute(route:$route, refresh:$refresh, baseUrl: $this->blaze_base_url);
        }

        foreach ($this->blaze['private'] as $route) {
            $service->invalidatePrivateRoute(route:$route, refresh:$refresh, baseUrl: $this->blaze_base_url);
        }
    }
}
