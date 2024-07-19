<?php

namespace Mvaliolahi\Blaze\Traits;

use Illuminate\Support\Facades\File;

trait FileUtils
{
    public function existCacheFile($filename)
    {
        return File::exists($filename);
    }

    public function retrieveCacheFile($filename)
    {
        return File::get($filename);
    }

    public function createCacheFile($filename, $content)
    {
        File::put($filename, $content);
    }

    private function createCacheDirectoryIfDoesNotExists($path)
    {
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
    }
}
