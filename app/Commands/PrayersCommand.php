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
                            {--action=timings : What do you want to do? Available actions: timings, calendar, hijricalendar, currentdate, currenttime, currenttimestamp, methods, playadhan}
                            {--date= : Date for prayer times (format: DD-MM-YYYY)}
                            {--year= : Year for calendar}
                            {--month= : Month for calendar}
                            {--city=Manama : City name}
                            {--country=Bahrain : Country name or code}
                            {--method=10 : Calculation method ID}
                            {--timezone=Asia/Bahrain : Timezone (e.g., Asia/Bahrain)}
                            {--next : Show the next prayer and time difference}';

    protected $aliases = ['prayers'];

    protected $description = 'Get prayer times and related information';

    private const DEFAULT_CITY = 'Manama';

    private const DEFAULT_COUNTRY = 'Bahrain';

    private const DEFAULT_METHOD = 10;

    public const DEFAULT_TIMEZONE = 'Asia/Bahrain';

    private const CACHE_DURATION = 86400; // 24 hours in seconds

    public const PRAYER_NAMES = ['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];

    private $baseUrl = 'http://api.aladhan.com/v1/';

    public function handle()
    {
        $this->displayBanner();

        try {
            $action = $this->option('action');
            $date = $this->option('date') ?? date('d-m-Y');
            $year = $this->option('year') ?? date('Y');
            $month = $this->option('month') ?? date('m');
            $city = $this->option('city');
            $country = $this->option('country');
            $method = $this->option('method');
            $timezone = $this->option('timezone');
            $showNext = $this->option('next');

            // Validate inputs
            if (! in_array($action, ['timings', 'calendar', 'hijricalendar', 'currentdate', 'currenttime', 'currenttimestamp', 'methods', 'playadhan'])) {
                throw new InvalidArgumentException("Oops! Invalid action: $action. Please check your input.");
            }

            if ($action === 'timings' && ! preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
                throw new InvalidArgumentException('Oops! Invalid date format. Please use DD-MM-YYYY.');
            }

            if (($action === 'calendar' || $action === 'hijricalendar') &&
                (! is_numeric($year) || ! is_numeric($month) || $month < 1 || $month > 12)
            ) {
                throw new InvalidArgumentException('Oops! Invalid year or month. Please check your input.');
            }

            switch ($action) {
                case 'timings':
                    $response = $this->getTimings($date, $city, $country, $method);
                    if (isset($response['data']['timings'])) {
                        $prayerTimes = new PrayerTimes($response['data']['timings']);
                        $prayerTimes->displayTimings(true, $showNext);
                    } else {
                        throw new RuntimeException("Oops! I couldn't fetch the timings. Please try again later.");
                    }
                    break;

                case 'calendar':
                case 'hijricalendar':
                    $method = $action === 'calendar' ? 'getCalendar' : 'getHijriCalendar';
                    $response = $this->$method($year, $month, $city, $country, $method);
                    if (isset($response['data'])) {
                        $this->displayCalendar($response['data']);
                    } else {
                        throw new RuntimeException("Oops! I couldn't fetch the calendar. Please try again later.");
                    }
                    break;

                case 'currentdate':
                case 'currenttime':
                case 'currenttimestamp':
                    $method = 'get'.ucfirst($action);
                    $response = $this->$method($timezone);
                    $label = ucwords(preg_replace('/([A-Z])/', ' $1', $action));
                    $this->info("$label: ".($response['data'] ?? "Oops! I couldn't fetch the $action. Please try again later."));
                    break;

                case 'methods':
                    $this->getMethods();
                    break;

                case 'playadhan':
                    $this->playAdhan();
                    break;
            }
        } catch (InvalidArgumentException $e) {
            $this->error('Oops! '.$e->getMessage());

            return 1;
        } catch (RuntimeException $e) {
            $this->error('Oops! '.$e->getMessage());

            return 1;
        } catch (Exception $e) {
            $this->error('Oops! Something unexpected happened: '.$e->getMessage());

            return 1;
        }

        return 0;
    }

    private function displayBanner()
    {
        $banner = <<<BANNER
        ____  ____  ____  ____  ____  ____  ____  ____  ____
       ||P |||R |||A |||Y |||E |||R |||S |||C |||L |||I ||
       ||__|||__|||__|||__|||__|||__|||__|||__|||__|||__||
       |/__\|/__\|/__\|/__\|/__\|/__\|/__\|/__\|/__\|/__\|
        ____  ____  ____  ____  ____  ____  ____  ____  ____
       ||T |||I |||M |||E |||S |||C |||L |||I |||O |||N ||
       ||__|||__|||__|||__|||__|||__|||__|||__|||__|||__||
       |/__\|/__\|/__\|/__\|/__\|/__\|/__\|/__\|/__\|/__\|
        BANNER;

        $this->info($banner);
    }

    private function cache($endpoint)
    {
        $cacheKey = 'prayer_times_'.md5($endpoint);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = $this->makeRequest($endpoint);

        if ($response) {
            Cache::put($cacheKey, $response, now()->addSeconds(self::CACHE_DURATION));
        }

        return $response;
    }

    private function makeRequest($endpoint, $params = [])
    {
        $url = $this->baseUrl.$endpoint;
        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: PrayerTimes-CLI/1.0',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new RuntimeException('cURL error: '.$curlError);
        }

        if ($httpCode >= 400) {
            throw new RuntimeException('The API returned a status code: '.$httpCode);
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode the API response. It might be corrupted.');
        }

        return $decodedResponse;
    }

    public function getTimings($date, $city = self::DEFAULT_CITY, $country = self::DEFAULT_COUNTRY, $method = self::DEFAULT_METHOD)
    {
        return $this->cache("timingsByCity/$date?city=$city&country=$country&method=$method");
    }

    public function getCalendar($year, $month, $city = self::DEFAULT_CITY, $country = self::DEFAULT_COUNTRY, $method = self::DEFAULT_METHOD)
    {
        return $this->cache("calendarByCity/$year/$month?city=$city&country=$country&method=$method");
    }

    public function getHijriCalendar($year, $month, $city = self::DEFAULT_CITY, $country = self::DEFAULT_COUNTRY, $method = self::DEFAULT_METHOD)
    {
        return $this->cache("hijriCalendarByCity/$year/$month?city=$city&country=$country&method=$method");
    }

    public function getCurrentDate($timezone = self::DEFAULT_TIMEZONE)
    {
        return $this->cache("currentDate?zone=$timezone");
    }

    public function getCurrentTime($timezone = self::DEFAULT_TIMEZONE)
    {
        return $this->cache("currentTime?zone=$timezone");
    }

    public function getCurrentTimestamp($timezone = self::DEFAULT_TIMEZONE)
    {
        return $this->cache("currentTimestamp?zone=$timezone");
    }

    public function getMethods()
    {
        $response = $this->cache('methods');
        if (isset($response['data'])) {
            $this->info('Hey there! Here are the available calculation methods:');
            foreach ($response['data'] as $method => $details) {
                $this->line("{$details['id']}: {$details['name']}");
            }
        } else {
            $this->error("Sorry, I couldn't fetch the methods. Please try again later.");
        }

        return $response;
    }

    public function playAdhan()
    {
        $adhanFile = storage_path('adhan.mp3');
        if (!file_exists($adhanFile)) {
            $this->error("Adhan file not found at $adhanFile");
            return;
        }

        $this->info("Playing Adhan...");
        exec("ffplay -nodisp -autoexit $adhanFile");
    }

    private function displayCalendar($calendar)
    {
        foreach ($calendar as $day) {
            $this->info("{$day['date']['readable']}:");
            $prayerTimes = new PrayerTimes($day['timings']);
            $prayerTimes->displayTimings(true);
            $this->newLine();
        }
    }
}

class PrayerTimes
{
    private $timings;

    private $nextPrayer;

    private $nextPrayerTime;

    public function __construct($timings)
    {
        $this->validateTimings($timings);
        $this->timings = $timings;
        $this->calculateNextPrayer();
    }

    private function validateTimings($timings)
    {
        foreach (PrayersCommand::PRAYER_NAMES as $prayer) {
            if (! isset($timings[$prayer])) {
                throw new InvalidArgumentException("Hey! I'm missing the timing for $prayer. Please check your data.");
            }
            if (! preg_match('/^\d{2}:\d{2}/', $timings[$prayer])) {
                throw new InvalidArgumentException("Oops! The time format for $prayer is invalid. It should be in HH:MM format.");
            }
        }
    }

    private function calculateNextPrayer()
    {
        $now = new DateTime('now', new DateTimeZone(PrayersCommand::DEFAULT_TIMEZONE));

        $nextPrayer = null;
        $nextPrayerTime = null;

        foreach (PrayersCommand::PRAYER_NAMES as $prayer) {
            $timeWithoutOffset = explode(' ', $this->timings[$prayer])[0];
            $prayerTime = DateTime::createFromFormat('H:i', $timeWithoutOffset, new DateTimeZone(PrayersCommand::DEFAULT_TIMEZONE));

            if ($prayerTime < $now) {
                $prayerTime->modify('+1 day');
            }

            if ($nextPrayer === null || $prayerTime < $nextPrayerTime) {
                $nextPrayer = $prayer;
                $nextPrayerTime = $prayerTime;
            }
        }

        $this->nextPrayer = $nextPrayer;
        $this->nextPrayerTime = $nextPrayerTime;
    }

    public function displayTimings($highlightNext = true, $showNext = true)
    {
        $prayers = PrayersCommand::PRAYER_NAMES;

        foreach ($prayers as $prayer) {
            $line = str_pad($prayer, 10).": {$this->timings[$prayer]}";
            if ($highlightNext && $prayer === $this->nextPrayer) {
                echo "\033[1;32m".$line." (Next)\033[0m\n";
            } else {
                echo $line."\n";
            }
        }

        if ($showNext || $this->nextPrayer !== null) {
            $timeDiff = (new DateTime('now', new DateTimeZone(PrayersCommand::DEFAULT_TIMEZONE)))->diff($this->nextPrayerTime);
            $timeDiffHuman = $this->formatTimeDiff($timeDiff);
            echo "Time remaining: $timeDiffHuman\n";
            echo "\nNext prayer: \033[1;32m$this->nextPrayer\033[0m in $timeDiffHuman\n";
        }
    }

    private function formatTimeDiff($interval)
    {
        $parts = [];
        if ($interval->d > 0) {
            $parts[] = $interval->d.' day'.($interval->d > 1 ? 's' : '');
        }
        if ($interval->h > 0) {
            $parts[] = $interval->h.' hour'.($interval->h > 1 ? 's' : '');
        }
        if ($interval->i > 0) {
            $parts[] = $interval->i.' minute'.($interval->i > 1 ? 's' : '');
        }
        if ($interval->s > 0) {
            $parts[] = $interval->s.' second'.($interval->s > 1 ? 's' : '');
        }

        return implode(' and ', $parts);
    }
}
