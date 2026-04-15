<?php

namespace App\Commands;

use Illuminate\Console\Command;
use App\Services\VersionService;

class VersionCommand extends Command
{
    protected $signature = 'app:version
                            {--show : Show current version}
                            {--increment : Increment version number}';

    protected $description = 'Manage application version';

    public function handle()
    {
        $versionService = new VersionService();

        if ($this->option('show')) {
            $currentVersion = $versionService->getCurrentVersion();
            $this->info("Current version: $currentVersion");
        } elseif ($this->option('increment')) {
            $currentVersion = $versionService->getCurrentVersion();
            $newVersion = $versionService->incrementVersion($currentVersion);
            $versionService->updateVersion($newVersion);
            $this->info("Version updated from $currentVersion to $newVersion");
        } else {
            $this->info('Use --show to display current version or --increment to increment version');
        }

        return 0;
    }
}
