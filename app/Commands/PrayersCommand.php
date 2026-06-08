<?php

namespace App\Commands;

use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

class PrayersCommand extends Command
{
    protected $signature = 'prayers:times
                            {--action=timings : Action to perform (timings|calendar|hijricalendar|currentdate|currenttime|currenttimestamp|methods|playadhan)}
                            {--date= : Date for prayer times (DD-MM-YYYY, defaults to today)}
                            {--year= : Year for calendar (defaults to current year)}
                            {--month= : Month for calendar (defaults to current month)}
                            {--city=Manama : City name}
                            {--country=Bahrain : Country name or ISO code}
                            {--method=10 : Calculation method ID (use --action=methods to list)}
                            {--timezone=Asia/Bahrain : Timezone (e.g. Asia/Bahrain, Africa/Cairo)}
                            {--next : Show the next prayer and countdown}
                            {--prayer= : Specific prayer for Adhan (Fajr|Dhuhr|Asr|Maghrib|Isha)}
                            {--variant=doha : Adhan variant (doha|makkah|madinah|generic)}
                            {--player=auto : Audio player (afplay|ffplay|auto)}';

    protected $aliases = ['prayers'];

    protected $description = 'Display prayer times, calendars, and play Adhan';

    private const DEFAULT_CITY = 'Manama';

    private const DEFAULT_COUNTRY = 'Bahrain';

    private const DEFAULT_METHOD = 10;

    public const DEFAULT_TIMEZONE = 'Asia/Bahrain';

    private const CACHE_DURATION = 86400; // 24 hours

    public const PRAYER_NAMES = ['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];

    private const VALID_ACTIONS = [
        'timings', 'calendar', 'hijricalendar',
        'currentdate', 'currenttime', 'currenttimestamp',
        'methods', 'playadhan',
    ];

    private const VALID_PRAYERS = ['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];

    private const VALID_VARIANTS = ['doha', 'makkah', 'madinah', 'generic'];

    private const VALID_PLAYERS = ['afplay', 'ffplay', 'auto'];

    /** @var non-empty-string */
    private string $baseUrl = 'http://api.aladhan.com/v1/';

    public function handle(): int
    {
        $this->displayBanner();

        try {
            $action = $this->option('action');
            $date = $this->option('date') ?? date('d-m-Y');
            $year = $this->option('year') ?? date('Y');
            $month = $this->option('month') ?? date('m');
            $city = $this->option('city');
            $country = $this->option('country');
            $calcMethod = $this->option('method');
            $timezone = $this->option('timezone');
            $showNext = (bool) $this->option('next');
            $selectedPrayer = $this->option('prayer');
            $selectedVariant = $this->option('variant');
            $selectedPlayer = $this->option('player');

            // --- Validate inputs ---
            if (! in_array($action, self::VALID_ACTIONS, true)) {
                throw new InvalidArgumentException(
                    "Invalid action: \"$action\". Valid actions: " . implode(', ', self::VALID_ACTIONS)
                );
            }

            if ($selectedPrayer && ! in_array($selectedPrayer, self::VALID_PRAYERS, true)) {
                throw new InvalidArgumentException(
                    "Invalid prayer: \"$selectedPrayer\". Valid options: " . implode(', ', self::VALID_PRAYERS)
                );
            }

            if (! in_array($selectedVariant, self::VALID_VARIANTS, true)) {
                throw new InvalidArgumentException(
                    "Invalid variant: \"$selectedVariant\". Valid options: " . implode(', ', self::VALID_VARIANTS)
                );
            }

            if (! in_array($selectedPlayer, self::VALID_PLAYERS, true)) {
                throw new InvalidArgumentException(
                    "Invalid player: \"$selectedPlayer\". Valid options: " . implode(', ', self::VALID_PLAYERS)
                );
            }

            if ($action === 'timings' && ! preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
                throw new InvalidArgumentException('Invalid date format. Please use DD-MM-YYYY.');
            }

            if (in_array($action, ['calendar', 'hijricalendar'], true)
                && (! is_numeric($year) || ! is_numeric($month) || $month < 1 || $month > 12)
            ) {
                throw new InvalidArgumentException('Invalid year or month.');
            }

            // --- Dispatch action ---
            switch ($action) {
                case 'timings':
                    $response = $this->getTimings($date, $city, $country, $calcMethod);
                    if (isset($response['data']['timings'])) {
                        $prayerTimes = new PrayerTimes($response['data']['timings']);
                        $prayerTimes->displayTimings(highlightNext: true, showNext: $showNext);
                    } else {
                        throw new RuntimeException("Could not fetch timings. Please try again later.");
                    }
                    break;

                case 'calendar':
                case 'hijricalendar':
                    // FIX: use a separate variable so $calcMethod is not overwritten
                    $calendarMethod = $action === 'calendar' ? 'getCalendar' : 'getHijriCalendar';
                    $response = $this->$calendarMethod($year, $month, $city, $country, $calcMethod);
                    if (isset($response['data'])) {
                        $this->displayCalendar($response['data']);
                    } else {
                        throw new RuntimeException("Could not fetch the calendar. Please try again later.");
                    }
                    break;

                case 'currentdate':
                case 'currenttime':
                case 'currenttimestamp':
                    // FIX: explicit map avoids ucfirst case mismatch (getCurrentdate vs getCurrentDate)
                    $methodMap = [
                        'currentdate'      => 'getCurrentDate',
                        'currenttime'      => 'getCurrentTime',
                        'currenttimestamp' => 'getCurrentTimestamp',
                    ];
                    $response = $this->{$methodMap[$action]}($timezone);
                    $label = ['currentdate' => 'Current Date', 'currenttime' => 'Current Time', 'currenttimestamp' => 'Current Timestamp'][$action];
                    $this->info("$label: " . ($response['data'] ?? "Could not fetch $action."));
                    break;

                case 'methods':
                    $this->getMethods();
                    break;

                case 'playadhan':
                    $this->playAdhan($selectedPrayer, $selectedVariant, $selectedPlayer);
                    break;
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        } catch (Exception $e) {
            $this->error('Unexpected error: ' . $e->getMessage());

            return 1;
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // Banner
    // -------------------------------------------------------------------------

    private function displayBanner(): void
    {
        $version = config('app.version', '');
        $versionLabel = $version ? "v$version" : '';

        $this->line('');
        $this->line('  <fg=green>╔══════════════════════════════════════════╗</>');
        $this->line("  <fg=green>║</>  <fg=yellow;options=bold>P R A Y E R S   C L I</>  <fg=white>$versionLabel</>          <fg=green>║</>");
        $this->line('  <fg=green>╠══════════════════════════════════════════╣</>');
        $this->line('  <fg=green>║</>    Islamic Prayer Times & Adhan Player    <fg=green>║</>');
        $this->line('  <fg=green>╚══════════════════════════════════════════╝</>');
        $this->line('');
    }

    // -------------------------------------------------------------------------
    // API / Cache
    // -------------------------------------------------------------------------

    private function cache(string $endpoint): mixed
    {
        $cacheKey = 'prayer_times_' . md5($endpoint);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = $this->makeRequest($endpoint);

        if ($response) {
            Cache::put($cacheKey, $response, now()->addSeconds(self::CACHE_DURATION));
        }

        return $response;
    }

    private function makeRequest(string $endpoint): mixed
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['User-Agent: PrayerTimes-CLI/1.0'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new RuntimeException("Network error: $curlError");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("API returned HTTP $httpCode.");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode API response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // API wrappers
    // -------------------------------------------------------------------------

    public function getTimings(
        string $date,
        string $city = self::DEFAULT_CITY,
        string $country = self::DEFAULT_COUNTRY,
        int|string $method = self::DEFAULT_METHOD
    ): mixed {
        return $this->cache("timingsByCity/$date?city=$city&country=$country&method=$method");
    }

    public function getCalendar(
        int|string $year,
        int|string $month,
        string $city = self::DEFAULT_CITY,
        string $country = self::DEFAULT_COUNTRY,
        int|string $method = self::DEFAULT_METHOD
    ): mixed {
        return $this->cache("calendarByCity/$year/$month?city=$city&country=$country&method=$method");
    }

    public function getHijriCalendar(
        int|string $year,
        int|string $month,
        string $city = self::DEFAULT_CITY,
        string $country = self::DEFAULT_COUNTRY,
        int|string $method = self::DEFAULT_METHOD
    ): mixed {
        return $this->cache("hijriCalendarByCity/$year/$month?city=$city&country=$country&method=$method");
    }

    public function getCurrentDate(string $timezone = self::DEFAULT_TIMEZONE): mixed
    {
        return $this->cache("currentDate?zone=$timezone");
    }

    public function getCurrentTime(string $timezone = self::DEFAULT_TIMEZONE): mixed
    {
        return $this->cache("currentTime?zone=$timezone");
    }

    public function getCurrentTimestamp(string $timezone = self::DEFAULT_TIMEZONE): mixed
    {
        return $this->cache("currentTimestamp?zone=$timezone");
    }

    public function getMethods(): mixed
    {
        $response = $this->cache('methods');
        if (isset($response['data'])) {
            $this->info('Available calculation methods:');
            foreach ($response['data'] as $details) {
                $this->line("  {$details['id']}: {$details['name']}");
            }
        } else {
            $this->error("Could not fetch methods. Please try again later.");
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Adhan playback
    // -------------------------------------------------------------------------

    public function playAdhan(
        ?string $selectedPrayer = null,
        string $selectedVariant = 'doha',
        string $selectedPlayer = 'auto'
    ): void {
        $adhanDir = storage_path('adhan');

        if (! is_dir($adhanDir)) {
            $this->error("Adhan directory not found at: $adhanDir");

            return;
        }

        $variants = [
            'doha' => [
                'name'  => 'Doha, Qatar',
                'files' => [
                    'Fajr'   => 'doha/fajr.mp3',
                    'Dhuhr'  => 'doha/dhuhr.mp3',
                    'Asr'    => 'doha/asr.mp3',
                    'Maghrib' => 'doha/maghrib.mp3',
                    'Isha'   => 'doha/isha.mp3',
                ],
            ],
            'makkah' => [
                'name'  => 'Makkah Al-Mukarramah',
                'files' => [
                    'Fajr'   => 'makkah/fajr.mp3',
                    'Dhuhr'  => 'makkah/dhuhr.mp3',
                    'Asr'    => 'makkah/asr.mp3',
                    'Maghrib' => 'makkah/maghrib.mp3',
                    'Isha'   => 'makkah/isha.mp3',
                ],
            ],
            'madinah' => [
                'name'  => 'Madinah Al-Munawwarah',
                'files' => [
                    'Fajr'   => 'madinah/fajr.mp3',
                    'Dhuhr'  => 'madinah/dhuhr.mp3',
                    'Asr'    => 'madinah/asr.mp3',
                    'Maghrib' => 'madinah/maghrib.mp3',
                    'Isha'   => 'madinah/isha.mp3',
                ],
            ],
            'generic' => [
                'name'  => 'Generic Prayer Tone',
                'files' => [
                    'Fajr'   => 'generated/fajr_alert.mp3',
                    'Dhuhr'  => 'generated/prayer_tone.mp3',
                    'Asr'    => 'generated/prayer_tone.mp3',
                    'Maghrib' => 'generated/prayer_tone.mp3',
                    'Isha'   => 'generated/prayer_tone.mp3',
                ],
            ],
        ];

        $player = $this->detectPlayer($selectedPlayer);
        if (! $player) {
            $this->error('No audio player found. Install ffmpeg (ffplay) or use macOS (afplay).');

            return;
        }

        if ($selectedPrayer) {
            $this->playPrayerAdhan($selectedPrayer, $selectedVariant, $player, $adhanDir, $variants);

            return;
        }

        // Interactive menu
        $this->line('');
        $this->line('  <fg=green>Adhan Player</> — Select a variant to play');
        $this->line('');

        $choice = $this->choice(
            'Adhan variant:',
            array_map(fn ($key) => "{$variants[$key]['name']} ($key)", array_keys($variants)),
            0
        );

        preg_match('/\((\w+)\)/', $choice, $matches);
        $variantKey = $matches[1] ?? $selectedVariant;

        $this->line('');
        $this->line("  Variant: <fg=cyan>{$variants[$variantKey]['name']}</>");
        $this->line('');

        $prayerChoice = $this->choice(
            'Select prayer (or All):',
            ['All', 'Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'],
            0
        );

        $prayers = $prayerChoice === 'All'
            ? ['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha']
            : [$prayerChoice];

        foreach ($prayers as $prayer) {
            $this->playPrayerAdhan($prayer, $variantKey, $player, $adhanDir, $variants);
            if ($prayerChoice === 'All') {
                sleep(1);
            }
        }
    }

    private function playPrayerAdhan(
        string $prayer,
        string $variantKey,
        string $player,
        string $adhanDir,
        array $variants
    ): void {
        $variant = $variants[$variantKey] ?? null;
        if (! $variant) {
            $this->error("Variant \"$variantKey\" not found.");

            return;
        }

        $file = $variant['files'][$prayer] ?? null;
        if (! $file) {
            $this->error("No Adhan file mapped for $prayer in variant \"$variantKey\".");

            return;
        }

        $fullPath = "$adhanDir/$file";

        if (! file_exists($fullPath)) {
            $this->warn("File not found: $fullPath — trying Doha fallback...");
            $fullPath = "$adhanDir/doha/" . strtolower($prayer) . '.mp3';
            if (! file_exists($fullPath)) {
                $this->error("No Adhan file available for $prayer.");

                return;
            }
            $this->line("  Using fallback: Doha variant");
        }

        $this->line("  Playing Adhan for <fg=green;options=bold>$prayer</> ({$variant['name']}) via $player");

        $this->playAudioFile($fullPath, $player);

        $this->line("  Done: $prayer Adhan");
        $this->line('');
    }

    private function playAudioFile(string $filePath, string $player): void
    {
        $escaped = escapeshellarg($filePath);
        $command = $player === 'afplay'
            ? "afplay $escaped"
            : "ffplay -nodisp -autoexit -loglevel quiet $escaped";

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->warn("Player exited with code $exitCode.");
        }
    }

    private function detectPlayer(string $preference = 'auto'): ?string
    {
        if ($preference !== 'auto') {
            return $preference;
        }

        if (PHP_OS_FAMILY === 'Darwin' && exec('which afplay 2>/dev/null')) {
            return 'afplay';
        }

        if (exec('which ffplay 2>/dev/null')) {
            return 'ffplay';
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Calendar display
    // -------------------------------------------------------------------------

    private function displayCalendar(array $calendar): void
    {
        foreach ($calendar as $day) {
            $this->info($day['date']['readable'] . ':');
            $prayerTimes = new PrayerTimes($day['timings']);
            $prayerTimes->displayTimings(highlightNext: false, showNext: false);
            $this->newLine();
        }
    }
}

// =============================================================================
// PrayerTimes — display helper
// =============================================================================

class PrayerTimes
{
    private array $timings;

    private ?string $nextPrayer = null;

    private ?DateTime $nextPrayerTime = null;

    public function __construct(array $timings)
    {
        $this->validateTimings($timings);
        $this->timings = $timings;
        $this->calculateNextPrayer();
    }

    private function validateTimings(array $timings): void
    {
        foreach (PrayersCommand::PRAYER_NAMES as $prayer) {
            if (! isset($timings[$prayer])) {
                throw new InvalidArgumentException("Missing timing for $prayer.");
            }
            if (! preg_match('/^\d{2}:\d{2}/', $timings[$prayer])) {
                throw new InvalidArgumentException("Invalid time format for $prayer (expected HH:MM).");
            }
        }
    }

    private function calculateNextPrayer(): void
    {
        $tz = new DateTimeZone(PrayersCommand::DEFAULT_TIMEZONE);
        $now = new DateTime('now', $tz);

        foreach (PrayersCommand::PRAYER_NAMES as $prayer) {
            $timeStr = explode(' ', $this->timings[$prayer])[0];
            $prayerTime = DateTime::createFromFormat('H:i', $timeStr, $tz);

            if ($prayerTime < $now) {
                $prayerTime->modify('+1 day');
            }

            if ($this->nextPrayerTime === null || $prayerTime < $this->nextPrayerTime) {
                $this->nextPrayer = $prayer;
                $this->nextPrayerTime = $prayerTime;
            }
        }
    }

    public function displayTimings(bool $highlightNext = true, bool $showNext = true): void
    {
        foreach (PrayersCommand::PRAYER_NAMES as $prayer) {
            $line = str_pad($prayer, 10) . ': ' . $this->timings[$prayer];
            if ($highlightNext && $prayer === $this->nextPrayer) {
                echo "\033[1;32m" . $line . "  (Next)\033[0m\n";
            } else {
                echo $line . "\n";
            }
        }

        // FIX: only show countdown when --next flag is set (was always true before)
        if ($showNext && $this->nextPrayer !== null && $this->nextPrayerTime !== null) {
            $tz = new DateTimeZone(PrayersCommand::DEFAULT_TIMEZONE);
            $diff = (new DateTime('now', $tz))->diff($this->nextPrayerTime);
            $human = $this->formatTimeDiff($diff);

            echo "\n";
            echo "Next prayer : \033[1;32m{$this->nextPrayer}\033[0m in $human\n";
        }
    }

    private function formatTimeDiff(\DateInterval $interval): string
    {
        $parts = [];

        if ($interval->d > 0) {
            $parts[] = $interval->d . ' day' . ($interval->d !== 1 ? 's' : '');
        }
        if ($interval->h > 0) {
            $parts[] = $interval->h . ' hr' . ($interval->h !== 1 ? 's' : '');
        }
        if ($interval->i > 0) {
            $parts[] = $interval->i . ' min' . ($interval->i !== 1 ? 's' : '');
        }
        if ($interval->s > 0 && $interval->d === 0) {
            $parts[] = $interval->s . ' sec' . ($interval->s !== 1 ? 's' : '');
        }

        return implode(' ', $parts);
    }
}
