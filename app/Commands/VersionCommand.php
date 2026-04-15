<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VersionCommand extends Command
{
    protected $signature = 'app:version
                            {--auto-increment : Auto-increment the version number}';

    protected $description = 'Manage application version';

    public function handle()
    {
        $versionFile = base_path('VERSION');
        $currentVersion = $this->getCurrentVersion($versionFile);

        if ($this->option('auto-increment')) {
            $newVersion = $this->incrementVersion($currentVersion);
            $this->updateVersionFile($versionFile, $newVersion);
            $this->info("Version updated from $currentVersion to $newVersion");
        } else {
            $this->info("Current version: $currentVersion");
        }

        return 0;
    }

    private function getCurrentVersion($versionFile)
    {
        if (!File::exists($versionFile)) {
            return '1.0.0';
        }

        return trim(File::get($versionFile));
    }

    private function incrementVersion($version)
    {
        $parts = explode('.', $version);
        $parts[2]++; // Increment patch version

        return implode('.', $parts);
    }

    private function updateVersionFile($versionFile, $version)
    {
        File::put($versionFile, $version);
    }
}
