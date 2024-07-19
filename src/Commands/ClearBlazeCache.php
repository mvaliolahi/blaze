<?php

namespace Mvaliolahi\Blaze\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearBlazeCache extends Command
{
    protected $signature = 'blaze:clear';
    protected $description = 'Clear the Blaze cache directory';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $cacheDir = public_path('cache');

        if (File::exists($cacheDir)) {
            File::cleanDirectory($cacheDir);
            $this->info('Blaze cache cleared successfully.');
        } else {
            $this->info('No Blaze cache directory found.');
        }
    }
}
