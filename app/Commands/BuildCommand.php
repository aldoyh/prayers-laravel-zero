<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BuildCommand extends Command
{
    protected $signature = 'app:build
                            {--auto-version : Auto-increment version number}';

    protected $description = 'Build the application';

    public function handle()
    {
        if ($this->option('auto-version')) {
            Artisan::call('app:version', ['--auto-increment' => true]);
        }

        // Add your build logic here
        $this->info('Building the application...');
        // ... rest of your build logic

        return 0;
    }
}
