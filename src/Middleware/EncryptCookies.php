<?php

namespace Mvaliolahi\Blaze;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    protected $except = [
        'blaze_id',
    ];
}
