<?php

namespace App\Commands;

use App\Services\AdhanService;
use App\Services\AudioPlayerService;
use App\Services\PrayerTimes;
use App\Services\PrayerTimesService;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Console\Command;

class PrayersCheckCommand extends Command
{
    protected $signature = 'prayers:check
                            {--city=Manama : City name}
                            {--country=Bahrain : Country name or ISO code}
                            {--method=10 : Calculation method ID}
                            {--timezone=Asia/Bahrain : Timezone}
                            {--variant=doha : Adhan variant (doha|makkah|madinah|generic)}
                            {--player=auto : Audio player (afplay|ffplay|auto)}
                            {--grace=2 : Grace window in minutes around prayer time}
                            {--log : Enable logging to stdout (default: silent)}
                            {--once : Only check the next upcoming prayer, then exit}
                            {--foreground : Keep running (check every ~30s) — for development testing}
                            {--state-file=/tmp/prayers-cli-state.json : State file to prevent double-firing}';

    protected $description = 'Check if it\'s time for prayer and play Adhan (cron-friendly)';

    private PrayerTimesService $prayerTimesService;

    private AdhanService $adhanService;

    private AudioPlayerService $audioPlayer;

    private string $timezone;

    public function __construct(
        ?PrayerTimesService $prayerTimesService = null,
        ?AdhanService $adhanService = null,
        ?AudioPlayerService $audioPlayer = null
    ) {
        parent::__construct();

        $this->prayerTimesService = $prayerTimesService ?? new PrayerTimesService;
        $this->adhanService = $adhanService ?? new AdhanService;
        $this->audioPlayer = $audioPlayer ?? new AudioPlayerService;
    }

    public function handle(): int
    {
        $city = $this->option('city');
        $country = $this->option('country');
        $method = $this->option('method');
        $this->timezone = $this->option('timezone');
        $variant = $this->option('variant');
        $playerPref = $this->option('player');
        $grace = (int) $this->option('grace');
        $logging = (bool) $this->option('log');
        $once = (bool) $this->option('once');
        $foreground = (bool) $this->option('foreground');
        $stateFile = $this->option('state-file');

        // --- Detect player ---
        $player = $this->audioPlayer->detect($playerPref);
        if ($player === null) {
            if ($logging) {
                $this->error('No audio player found. Install ffmpeg (ffplay) or use macOS (afplay).');
            }

            return 1;
        }

        if ($foreground) {
            return $this->runForeground($city, $country, $method, $variant, $player, $grace, $logging);
        }

        return $this->runOnce($city, $country, $method, $variant, $player, $grace, $logging, $once, $stateFile);
    }

    /**
     * Run a single check — used by cron/launchd.
     */
    private function runOnce(
        string $city,
        string $country,
        int|string $method,
        string $variant,
        string $player,
        int $grace,
        bool $logging,
        bool $onlyNext,
        string $stateFile
    ): int {
        try {
            $timings = $this->prayerTimesService->getTodayTimings($city, $country, $method);
            $prayerTimes = new PrayerTimes($timings, $this->timezone);

            $today = date('Y-m-d');
            $playedToday = $this->loadState($stateFile, $today);
            $played = false;

            $prayersToCheck = $onlyNext
                ? [$prayerTimes->getNextPrayerName()]
                : AdhanService::VALID_PRAYERS;

            foreach ($prayersToCheck as $prayer) {
                if ($prayer === null) {
                    continue;
                }

                if (in_array($prayer, $playedToday, true)) {
                    if ($logging) {
                        $this->line("  Already played $prayer today, skipping.");
                    }
                    continue;
                }

                if ($prayerTimes->isPrayerTime($prayer, $grace)) {
                    $this->playAdhanSilently($prayer, $variant, $player, $logging);
                    $playedToday[] = $prayer;
                    $played = true;
                }
            }

            // Save state so we don't replay
            $this->saveState($stateFile, $today, $playedToday);

            if (! $played && $logging) {
                $next = $prayerTimes->getNextPrayerName();
                $countdown = $prayerTimes->getNextPrayerCountdown();
                $this->line("Not yet time. Next prayer: $next in $countdown.");
            }

            return 0;
        } catch (Exception $e) {
            if ($logging) {
                $this->error('Error: ' . $e->getMessage());
            }

            return 1;
        }
    }

    /**
     * Foreground mode — loops every 30s (for testing or interactive use).
     */
    private function runForeground(
        string $city,
        string $country,
        int|string $method,
        string $variant,
        string $player,
        int $grace,
        bool $logging
    ): int {
        $this->line('  <fg=cyan>Prayers Daemon — Foreground Mode</>');
        $this->line('  Press Ctrl+C to stop.');
        $this->line('');

        $stateFile = sys_get_temp_dir() . '/prayers-cli-state.json';

        while (true) {
            try {
                $timings = $this->prayerTimesService->getTodayTimings($city, $country, $method);
                $prayerTimes = new PrayerTimes($timings, $this->timezone);
                $tz = new DateTimeZone($this->timezone);
                $now = new DateTime('now', $tz);
                $today = $now->format('Y-m-d');

                $playedToday = $this->loadState($stateFile, $today);

                $this->line('  [' . $now->format('H:i:s') . '] Checking...');

                foreach (AdhanService::VALID_PRAYERS as $prayer) {
                    if (in_array($prayer, $playedToday, true)) {
                        continue;
                    }

                    if ($prayerTimes->isPrayerTime($prayer, $grace)) {
                        $this->line("  🕌 Playing Adhan for <fg=green;options=bold>$prayer</>");
                        $this->playAdhanSilently($prayer, $variant, $player, true);
                        $playedToday[] = $prayer;
                    }
                }

                $this->saveState($stateFile, $today, $playedToday);

                $next = $prayerTimes->getNextPrayerName();
                $countdown = $prayerTimes->getNextPrayerCountdown();
                $this->line('  Next: <fg=yellow>' . ($next ?? 'N/A') . '</> in ' . ($countdown ?? 'N/A'));
                $this->line('');

                sleep(30);
            } catch (Exception $e) {
                $this->error('Error: ' . $e->getMessage());
                sleep(60);
            }
        }
    }

    /**
     * Play Adhan with minimal output (silent mode).
     */
    private function playAdhanSilently(string $prayer, string $variant, string $player, bool $logging): void
    {
        $filePath = $this->adhanService->resolvePath($prayer, $variant);

        if ($filePath === null) {
            if ($logging) {
                $this->warn("No Adhan file for $prayer (variant: $variant)");
            }

            return;
        }

        $this->audioPlayer->play($filePath, $player);

        if ($logging) {
            $this->line("  ✅ Adhan played for <fg=green>$prayer</>");
        }
    }

    // -------------------------------------------------------------------------
    // State file — prevents double-firing Adhan across cron/launchd invocations
    // -------------------------------------------------------------------------

    /**
     * Load the list of prayers already played today (with file locking).
     *
     * @return string[]
     */
    private function loadState(string $stateFile, string $today): array
    {
        if (! file_exists($stateFile)) {
            return [];
        }

        $fh = @fopen($stateFile, 'rb');
        if ($fh === false) {
            return [];
        }

        // Exclusive (write) lock — prevents concurrent reads during write window
        flock($fh, LOCK_SH);
        $contents = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        if ($contents === false || $contents === '') {
            return [];
        }

        $data = json_decode($contents, true);
        if (! is_array($data)) {
            return [];
        }

        // If the state is from a different day, reset
        if (($data['date'] ?? '') !== $today) {
            return [];
        }

        return $data['played'] ?? [];
    }

    /**
     * Save the list of prayers played today (with file locking).
     *
     * @param  string[] $played
     */
    private function saveState(string $stateFile, string $today, array $played): void
    {
        $fh = @fopen($stateFile, 'cb');
        if ($fh === false) {
            return;
        }

        // Exclusive lock — blocks until we have sole access
        flock($fh, LOCK_EX);

        $data = [
            'date'   => $today,
            'played' => array_values(array_unique($played)),
        ];

        ftruncate($fh, 0);
        fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fh);

        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
