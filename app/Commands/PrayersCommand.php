<?php

namespace App\Commands;

use App\Services\AdhanService;
use App\Services\AudioPlayerService;
use App\Services\PrayerTimes;
use App\Services\PrayerTimesService;
use Exception;
use Illuminate\Console\Command;
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

    private PrayerTimesService $prayerTimesService;

    private AdhanService $adhanService;

    private AudioPlayerService $audioPlayer;

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
            if (! PrayerTimesService::isValidAction($action)) {
                throw new InvalidArgumentException(
                    "Invalid action: \"$action\"."
                );
            }

            if ($selectedPrayer) {
                AdhanService::validatePrayer($selectedPrayer);
            }

            AdhanService::validateVariant($selectedVariant);

            AudioPlayerService::validatePlayer($selectedPlayer);

            // --- Dispatch action ---
            return match ($action) {
                'timings'          => $this->handleTimings($date, $city, $country, $calcMethod, $showNext),
                'calendar'         => $this->handleCalendar('getCalendar', $year, $month, $city, $country, $calcMethod),
                'hijricalendar'    => $this->handleCalendar('getHijriCalendar', $year, $month, $city, $country, $calcMethod),
                'currentdate'      => $this->handleCurrent('Current Date', 'getCurrentDate', $timezone),
                'currenttime'      => $this->handleCurrent('Current Time', 'getCurrentTime', $timezone),
                'currenttimestamp' => $this->handleCurrent('Current Timestamp', 'getCurrentTimestamp', $timezone),
                'methods'          => $this->handleMethods(),
                'playadhan'        => $this->handlePlayAdhan($selectedPrayer, $selectedVariant, $selectedPlayer),
            };
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
    // Action handlers
    // -------------------------------------------------------------------------

    private function handleTimings(
        string $date,
        string $city,
        string $country,
        int|string $method,
        bool $showNext
    ): int {
        $response = $this->prayerTimesService->getTimings($date, $city, $country, $method);

        if (! isset($response['data']['timings'])) {
            $this->error('Could not fetch timings. Please try again later.');

            return 1;
        }

        $prayerTimes = new PrayerTimes($response['data']['timings']);
        $prayerTimes->displayTimings(highlightNext: true, showNext: $showNext);

        return 0;
    }

    private function handleCalendar(
        string $methodName,
        int|string $year,
        int|string $month,
        string $city,
        string $country,
        int|string $method
    ): int {
        $response = $this->prayerTimesService->$methodName($year, $month, $city, $country, $method);

        if (! isset($response['data'])) {
            $this->error('Could not fetch the calendar. Please try again later.');

            return 1;
        }

        $this->displayCalendar($response['data']);

        return 0;
    }

    private function handleCurrent(string $label, string $methodName, string $timezone): int
    {
        $response = $this->prayerTimesService->$methodName($timezone);
        $data = $response['data'] ?? "Could not fetch $label.";

        $this->info("$label: $data");

        return 0;
    }

    private function handleMethods(): int
    {
        $response = $this->prayerTimesService->getMethods();

        if (! isset($response['data'])) {
            $this->error('Could not fetch methods. Please try again later.');

            return 1;
        }

        $this->info('Available calculation methods:');
        foreach ($response['data'] as $details) {
            if (! isset($details['id']) || ! isset($details['name'])) {
                continue;
            }
            $this->line("  {$details['id']}: {$details['name']}");
        }

        return 0;
    }

    private function handlePlayAdhan(
        ?string $selectedPrayer,
        string $selectedVariant,
        string $selectedPlayer
    ): int {
        if ($selectedPrayer) {
            $this->playSingleAdhan($selectedPrayer, $selectedVariant, $selectedPlayer);

            return 0;
        }

        // Interactive menu
        $this->line('');
        $this->line('  <fg=green>Adhan Player</> — Select a variant to play');
        $this->line('');

        $variants = $this->adhanService->getVariants();
        $variantKeys = array_keys($variants);

        $choice = $this->choice(
            'Adhan variant:',
            array_map(fn (string $key) => "{$variants[$key]['name']} ($key)", $variantKeys),
            0
        );

        preg_match('/\((\w+)\)/', $choice, $matches);
        $variantKey = $matches[1] ?? $selectedVariant;

        $this->line('');
        $this->line("  Variant: <fg=cyan>{$variants[$variantKey]['name']}</>");
        $this->line('');

        $prayerChoice = $this->choice(
            'Select prayer (or All):',
            ['All', ...AdhanService::VALID_PRAYERS],
            0
        );

        $prayers = $prayerChoice === 'All'
            ? AdhanService::VALID_PRAYERS
            : [$prayerChoice];

        foreach ($prayers as $prayer) {
            $this->playSingleAdhan($prayer, $variantKey, $selectedPlayer);
            if ($prayerChoice === 'All') {
                sleep(1);
            }
        }

        return 0;
    }

    private function playSingleAdhan(string $prayer, string $variant, string $playerPref): void
    {
        $player = $this->audioPlayer->detect($playerPref);

        if ($player === null) {
            $this->error('No audio player found. Install ffmpeg (ffplay) or use macOS (afplay).');

            return;
        }

        $filePath = $this->adhanService->resolvePath($prayer, $variant);

        if ($filePath === null) {
            $this->error("No Adhan file available for $prayer.");

            return;
        }

        $variantName = $this->adhanService->getVariantName($variant);
        $this->line("  Playing Adhan for <fg=green;options=bold>$prayer</> ($variantName) via $player");

        $this->audioPlayer->play($filePath, $player);

        $this->line("  Done: $prayer Adhan");
        $this->line('');
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
