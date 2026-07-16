<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

class AdhanService
{
    public const VALID_PRAYERS = ['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];

    public const VALID_VARIANTS = ['doha', 'makkah', 'madinah', 'generic'];

    private AudioPlayerService $audioPlayer;

    private array $variants;

    public function __construct(?AudioPlayerService $audioPlayer = null)
    {
        $this->audioPlayer = $audioPlayer ?? new AudioPlayerService;
        $this->variants = $this->buildVariants();
    }

    /**
     * Validate a prayer name.
     */
    public static function validatePrayer(string $prayer): void
    {
        if (! in_array($prayer, self::VALID_PRAYERS, true)) {
            throw new InvalidArgumentException(
                "Invalid prayer: \"$prayer\". Valid options: " . implode(', ', self::VALID_PRAYERS)
            );
        }
    }

    /**
     * Validate a variant key.
     */
    public static function validateVariant(string $variant): void
    {
        if (! in_array($variant, self::VALID_VARIANTS, true)) {
            throw new InvalidArgumentException(
                "Invalid variant: \"$variant\". Valid options: " . implode(', ', self::VALID_VARIANTS)
            );
        }
    }

    /**
     * Get all variant definitions.
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * Get the display name for a variant.
     */
    public function getVariantName(string $variantKey): string
    {
        self::validateVariant($variantKey);

        return $this->variants[$variantKey]['name'];
    }

    /**
     * Resolve the full path to an Adhan audio file for a given prayer and variant.
     * Falls back to Doha if the requested variant doesn't have the file.
     *
     * @return string|null Absolute path, or null if not found (even with fallback)
     */
    public function resolvePath(string $prayer, string $variant): ?string
    {
        self::validatePrayer($prayer);
        self::validateVariant($variant);

        $adhanDir = storage_path('adhan');

        // Try the specified variant first
        $relativePath = $this->variants[$variant]['files'][$prayer] ?? null;
        if ($relativePath) {
            $fullPath = "$adhanDir/$relativePath";
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // Fallback: try doha/{prayer_lower}.mp3
        $fallbackPath = "$adhanDir/doha/" . strtolower($prayer) . '.mp3';
        if (file_exists($fallbackPath)) {
            return $fallbackPath;
        }

        return null;
    }

    /**
     * Play the Adhan for a given prayer and variant.
     * Returns true if playback was successful, false otherwise.
     */
    public function play(string $prayer, string $variant = 'doha', ?string $player = null): bool
    {
        self::validatePrayer($prayer);
        self::validateVariant($variant);

        $filePath = $this->resolvePath($prayer, $variant);

        if ($filePath === null) {
            return false;
        }

        $player = $player ?? $this->audioPlayer->detect('auto');
        if ($player === null) {
            return false;
        }

        return $this->audioPlayer->play($filePath, $player, "$prayer Adhan ({$this->getVariantName($variant)})");
    }

    /**
     * Build the variant definitions.
     */
    private function buildVariants(): array
    {
        return [
            'doha' => [
                'name'  => 'Doha, Qatar',
                'files' => [
                    'Fajr'    => 'doha/fajr.mp3',
                    'Dhuhr'   => 'doha/dhuhr.mp3',
                    'Asr'     => 'doha/asr.mp3',
                    'Maghrib' => 'doha/maghrib.mp3',
                    'Isha'    => 'doha/isha.mp3',
                ],
            ],
            'makkah' => [
                'name'  => 'Makkah Al-Mukarramah',
                'files' => [
                    'Fajr'    => 'makkah/fajr.mp3',
                    'Dhuhr'   => 'makkah/dhuhr.mp3',
                    'Asr'     => 'makkah/asr.mp3',
                    'Maghrib' => 'makkah/maghrib.mp3',
                    'Isha'    => 'makkah/isha.mp3',
                ],
            ],
            'madinah' => [
                'name'  => 'Madinah Al-Munawwarah',
                'files' => [
                    'Fajr'    => 'madinah/fajr.mp3',
                    'Dhuhr'   => 'madinah/dhuhr.mp3',
                    'Asr'     => 'madinah/asr.mp3',
                    'Maghrib' => 'madinah/maghrib.mp3',
                    'Isha'    => 'madinah/isha.mp3',
                ],
            ],
            'generic' => [
                'name'  => 'Generic Prayer Tone',
                'files' => [
                    'Fajr'    => 'generated/fajr_alert.mp3',
                    'Dhuhr'   => 'generated/prayer_tone.mp3',
                    'Asr'     => 'generated/prayer_tone.mp3',
                    'Maghrib' => 'generated/prayer_tone.mp3',
                    'Isha'    => 'generated/prayer_tone.mp3',
                ],
            ],
        ];
    }
}
