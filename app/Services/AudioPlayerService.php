<?php

namespace App\Services;

use RuntimeException;

class AudioPlayerService
{
    public const VALID_PLAYERS = ['afplay', 'ffplay', 'auto'];

    private ?string $detectedPlayer = null;

    /**
     * Validate a player name.
     */
    public static function validatePlayer(string $player): void
    {
        if (! in_array($player, self::VALID_PLAYERS, true)) {
            throw new \InvalidArgumentException(
                "Invalid player: \"$player\". Valid options: " . implode(', ', self::VALID_PLAYERS)
            );
        }
    }

    /**
     * Detect the best available audio player.
     * Returns null if none found.
     */
    public function detect(string $preference = 'auto'): ?string
    {
        if ($preference !== 'auto') {
            if (in_array($preference, self::VALID_PLAYERS, true) && $this->isAvailable($preference)) {
                $this->detectedPlayer = $preference;

                return $preference;
            }

            return null;
        }

        // macOS native player
        if (PHP_OS_FAMILY === 'Darwin' && $this->isAvailable('afplay')) {
            $this->detectedPlayer = 'afplay';

            return 'afplay';
        }

        // Cross-platform via ffmpeg
        if ($this->isAvailable('ffplay')) {
            $this->detectedPlayer = 'ffplay';

            return 'ffplay';
        }

        $this->detectedPlayer = null;

        return null;
    }

    /**
     * Play an audio file using the specified player.
     *
     * @param  string      $filePath Absolute path to the audio file
     * @param  string      $player   Player name (afplay, ffplay)
     * @param  string|null $label    Optional label for logging
     * @return bool Success
     */
    public function play(string $filePath, string $player, ?string $label = null): bool
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("Audio file not found: $filePath");
        }

        $filePath = $this->trimSilenceFromEnd($filePath);
        $escaped = escapeshellarg($filePath);

        if ($player === 'afplay') {
            $command = "afplay $escaped";
        } elseif ($player === 'ffplay') {
            $command = "ffplay -nodisp -autoexit -loglevel quiet $escaped";
        } else {
            throw new RuntimeException("Unknown audio player: $player");
        }

        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    /**
     * Trim silence from the end of an audio file using ffmpeg.
     *
     * @param  string $filePath Original file path
     * @return string Path to the trimmed file (or original if ffmpeg unavailable)
     */
    public function trimSilenceFromEnd(string $filePath): string
    {
        // Only trim if ffmpeg is available
        exec('which ffmpeg 2>/dev/null', $ffmpegOutput, $ffmpegExit);
        if ($ffmpegExit !== 0) {
            return $filePath;
        }

        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'trimmed_') . '.mp3';

        $command = 'ffmpeg -i ' . escapeshellarg($filePath) .
                   ' -af silenceremove=1:0:-50dB -y ' . escapeshellarg($tempFile) . ' 2>&1';

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            @unlink($tempFile);

            return $filePath;
        }

        // Register shutdown function to clean up the temp file after playback
        register_shutdown_function(function () use ($tempFile): void {
            @unlink($tempFile);
        });

        return $tempFile;
    }

    /**
     * Check if a player binary is available on the system.
     */
    public function isAvailable(string $player): bool
    {
        $binary = match ($player) {
            'afplay' => 'afplay',
            'ffplay' => 'ffplay',
            default  => $player,
        };

        $result = exec("which $binary 2>/dev/null");

        return $result !== '' && $result !== null;
    }

    /**
     * Get the name of the detected player.
     */
    public function getDetectedPlayer(): ?string
    {
        return $this->detectedPlayer;
    }
}
