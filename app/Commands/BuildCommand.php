<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Services\VersionService;

class BuildCommand extends Command
{
    protected $signature = 'app:build
                            {--auto-version : Auto-increment version number}';

    protected $description = 'Build the application';

    public function handle()
    {
        $versionService = new VersionService();

        if ($this->option('auto-version')) {
            $currentVersion = $versionService->getCurrentVersion();
            $newVersion = $versionService->incrementVersion($currentVersion);
            $versionService->updateVersion($newVersion);
            $this->info("Version updated from $currentVersion to $newVersion");
        }

        // Add your build logic here
        $this->info('Building the application...');
        // ... rest of your build logic

        return 0;
    }
}
