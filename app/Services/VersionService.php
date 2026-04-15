<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class VersionService
{
    protected $versionFile;

    public function __construct()
    {
        $this->versionFile = base_path('VERSION');
    }

    public function getCurrentVersion()
    {
        if (!File::exists($this->versionFile)) {
            return '1.0.0';
        }

        return trim(File::get($this->versionFile));
    }

    public function incrementVersion($version)
    {
        $parts = explode('.', $version);
        $parts[2]++; // Increment patch version

        return implode('.', $parts);
    }

    public function updateVersion($version)
    {
        File::put($this->versionFile, $version);
    }
}
