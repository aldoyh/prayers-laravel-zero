<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class VersionService
{
    protected string $versionFile;

    public function __construct()
    {
        $this->versionFile = base_path('VERSION');
    }

    public function getCurrentVersion(): string
    {
        if (! File::exists($this->versionFile)) {
            return '1.0.0';
        }

        return trim(File::get($this->versionFile));
    }

    /**
     * Increment the patch segment (X.Y.PATCH).
     * Pass 'minor' or 'major' to bump those segments instead.
     */
    public function incrementVersion(string $version, string $segment = 'patch'): string
    {
        $parts = explode('.', $version);

        if (count($parts) !== 3 || ! array_all($parts, fn ($p) => is_numeric($p))) {
            throw new InvalidArgumentException("Version \"$version\" is not valid semver (X.Y.Z).");
        }

        [$major, $minor, $patch] = array_map('intval', $parts);

        switch ($segment) {
            case 'major':
                return ($major + 1) . '.0.0';
            case 'minor':
                return "$major." . ($minor + 1) . '.0';
            default: // patch
                return "$major.$minor." . ($patch + 1);
        }
    }

    public function updateVersion(string $version): void
    {
        File::put($this->versionFile, $version);
    }
}
