<?php

// disable deprecated warnings

// error_reporting(E_ALL & ~E_DEPRECATED);
// ini_set('display_errors', 0);
// ini_set('display_startup_errors', 0);

// Set the default timezone to Asia/Bahrain for consistent time calculations
date_default_timezone_set('Asia/Bahrain');



$cliOptions = getopt('d', ['debug']);

if ($cliOptions) {
    echo $cliOptions;
}

/**
 * Initializes the 7x
 * 
 * 
 * 
 */
include 'keys.php';
$x7xApiKey = $keys['7x'];
if (empty($x7xApiKey)) {
    die('No 7x key found.');
}


// =============================================================



class AlAdhanApiClient
{
    public const DEFAULT_CITY = 'Manama';
    public const DEFAULT_COUNTRY = 'Bahrain';
    public const DEFAULT_METHOD = 10;
    public const DEFAULT_TIMEZONE = 'Asia/Bahrain';
    private const CACHE_DURATION = 86400; // 24 hours in seconds

    private $baseUrl = 'http://api.aladhan.com/v1/';
    private $x7xApiKey;
    private $cacheDir;

    public function __construct($x7xApiKey)
    {
        $this->x7xApiKey = $x7xApiKey;
        $this->cacheDir = sys_get_temp_dir() . '/prayer-times-cache';
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function cache($endpoint)
    {
        $cacheFile = $this->cacheDir . '/' . md5($endpoint) . '.json';
        
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            if ($cacheData && time() < $cacheData['expires']) {
                return $cacheData['data'];
            }
        }

        $response = $this->makeRequest($endpoint);
        
        if ($response) {
            file_put_contents($cacheFile, json_encode([
                'data' => $response,
                'expires' => time() + self::CACHE_DURATION
            ]));
        }

        return $response;
    }

    private function makeRequest($endpoint, $params = [])
    {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'PrayerTimes-CLI/1.0'
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("API request failed: $error");
        }

        if ($statusCode !== 200) {
            throw new RuntimeException("API returned status code: $statusCode");
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode API response');
        }

        return $decodedResponse;
    }

    public function getTimings($date, $city = self::DEFAULT_CITY, $country = self::DEFAULT_COUNTRY, $method = self::DEFAULT_METHOD)
    {
        return $this->makeRequest("timingsByCity/$date", [
            'city' => $city,
            'country' => $country,
            'method' => $method,
        ]);
    }

    public function getCalendar($year, $month, $city = self::DEFAULT_CITY, $country = self::DEFAULT_COUNTRY, $method = self::DEFAULT_METHOD)
    {
        return $this->makeRequest("calendarByCity/$year/$month", [
            'city' => $city,
            'country' => $country,
            'method' => $method,
        ]);
    }

    public function getHijriCalendar($year, $month, $city = self::DEFAULT_CITY, $country = self::DEFAULT_COUNTRY, $method = self::DEFAULT_METHOD)
    {
        return $this->makeRequest("hijriCalendarByCity/$year/$month", [
            'city' => $city,
            'country' => $country,
            'method' => $method,
        ]);
    }

    public function getCurrentDate($timezone = self::DEFAULT_TIMEZONE)
    {
        return $this->makeRequest('currentDate', ['zone' => $timezone]);
    }

    public function getCurrentTime($timezone = self::DEFAULT_TIMEZONE)
    {
        return $this->makeRequest('currentTime', ['zone' => $timezone]);
    }

    public function getCurrentTimestamp($timezone = self::DEFAULT_TIMEZONE)
    {
        return $this->makeRequest('currentTimestamp', ['zone' => $timezone]);
    }

    public function getMethods()
    {
        $response = $this->cache('methods');
        if (isset($response['data'])) {
            echo "Available Methods:\n";
            foreach ($response['data'] as $method => $details) {
                echo "{$details['id']}: {$details['name']}\n";
            }
        } else {
            // You might want to display an error message here
            echo "Error: Unable to fetch methods.\n";
        }

        return $response;
    }

}

class PrayerTimes
{
    private const PRAYER_NAMES = ['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];
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
        foreach (self::PRAYER_NAMES as $prayer) {
            if (!isset($timings[$prayer])) {
                throw new InvalidArgumentException("Missing timing for prayer: $prayer");
            }
            if (!preg_match('/^\d{2}:\d{2}/', $timings[$prayer])) {
                throw new InvalidArgumentException("Invalid time format for prayer: $prayer");
            }
        }
    }

    private function calculateNextPrayer()
    {
        $now = new DateTime('now', new DateTimeZone('Asia/Bahrain')); // Adjust timezone as needed

        $nextPrayer = null;
        $nextPrayerTime = null;

        foreach (self::PRAYER_NAMES as $prayer) {
            $timeWithoutOffset = explode(' ', $this->timings[$prayer])[0];
            $prayerTime = DateTime::createFromFormat('H:i', $timeWithoutOffset, new DateTimeZone('Asia/Bahrain'));

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
        $prayers = self::PRAYER_NAMES;

        foreach ($prayers as $prayer) {
            $line = str_pad($prayer, 10) . ": {$this->timings[$prayer]}";
            if ($highlightNext && $prayer === $this->nextPrayer) {
                echo "\033[1;32m" . $line . " (Next)\033[0m\n";
            } else {
                echo $line . "\n";
            }
        }

        if ($showNext || $this->nextPrayer !== null) {
            $timeDiff = (new DateTime('now', new DateTimeZone('Asia/Bahrain')))->diff($this->nextPrayerTime);
            $timeDiffHuman = formatTimeDiff($timeDiff);
            echo "Time remaining: $timeDiffHuman\n";
            echo "\nNext prayer: \033[1;32m$this->nextPrayer\033[0m in $timeDiffHuman\n";
        }
    }
}

function formatTimeDiff($interval)
{
    $parts = [];
    if ($interval->d > 0) {
        $parts[] = $interval->d . " day" . ($interval->d > 1 ? "s" : "");
    }
    if ($interval->h > 0) {
        $parts[] = $interval->h . " hour" . ($interval->h > 1 ? "s" : "");
    }
    if ($interval->i > 0) {
        $parts[] = $interval->i . " minute" . ($interval->i > 1 ? "s" : "");
    }
    if ($interval->s > 0) {
        $parts[] = $interval->s . " second" . ($interval->s > 1 ? "s" : "");
    }
    return implode(" and ", $parts);
}

function displayCalendar($calendar)
{
    foreach ($calendar as $day) {
        echo "{$day['date']['readable']}:\n";
        $prayerTimes = new PrayerTimes($day['timings']);
        $prayerTimes->displayTimings(true);
        echo "\n";
    }
}

function displayHelp()
{
    echo "AlAdhan Prayer Times CLI App\n";
    echo "Usage: ./aladhan-cli.php [options]\n\n";
    echo "Options:\n";
    echo "  --help                Display this help message\n";
    echo "  --action=<action>     Specify the action to perform. Available actions:\n";
    echo "                        timings, calendar, hijricalendar, currentdate, currenttime,\n";
    echo "                        currenttimestamp, methods\n";
    echo "  --date=<date>         Date for prayer times (format: DD-MM-YYYY)\n";
    echo "  --year=<year>         Year for calendar\n";
    echo "  --month=<month>       Month for calendar\n";
    echo "  --city=<city>         City name\n";
    echo "  --country=<country>   Country name or code\n";
    echo "  --method=<method>     Calculation method ID\n";
    echo "  --timezone=<timezone> Timezone (e.g., Asia/Bahrain)\n";
    echo "  --next                Display the next prayer and time difference\n\n";
    echo "Examples:\n";
    echo "  1. Default (prayer times for Bahrain using method #10):\n";
    echo "     ./aladhan-cli.php\n\n";
    echo "  2. Get prayer times for a specific date and location:\n";
    echo "     ./aladhan-cli.php --action=timings --date=01-05-2023 --city=London --country=UK --method=2\n\n";
    echo "  3. Get calendar for a specific month:\n";
    echo "     ./aladhan-cli.php --action=calendar --year=2023 --month=5 --city=Dubai --country=AE\n\n";
    echo "  4. Get Hijri calendar:\n";
    echo "     ./aladhan-cli.php --action=hijricalendar --year=1444 --month=9\n\n";
    echo "  5. Get current date, time, or timestamp:\n";
    echo "     ./aladhan-cli.php --action=currentdate --timezone=America/New_York\n";
    echo "     ./aladhan-cli.php --action=currenttime --timezone=Europe/Paris\n";
    echo "     ./aladhan-cli.php --action=currenttimestamp\n\n";
    echo "  6. List available calculation methods:\n";
    echo "     ./aladhan-cli.php --action=methods\n\n";
    echo "  7. Display next prayer and time difference:\n";
    echo "     ./aladhan-cli.php --next\n";
}

/**
 * Function that creates a markdown file 
 * 
 * 
 */


$client = new AlAdhanApiClient($x7xApiKey);

try {
    $options = getopt("h", [
        "help",
        "action:",
        "date:",
        "year:",
        "month:",
        "city:",
        "country:",
        "method:",
        "timezone:",
        "next"
    ]);

    if (isset($options['h']) || isset($options['help'])) {
        displayHelp();
        exit(0);
    }

    $action = $options['action'] ?? 'timings';
    $date = $options['date'] ?? date('d-m-Y');
    $year = $options['year'] ?? date('Y');
    $month = $options['month'] ?? date('m');
    $city = $options['city'] ?? AlAdhanApiClient::DEFAULT_CITY;
    $country = $options['country'] ?? AlAdhanApiClient::DEFAULT_COUNTRY;
    $method = $options['method'] ?? AlAdhanApiClient::DEFAULT_METHOD;
    $timezone = $options['timezone'] ?? AlAdhanApiClient::DEFAULT_TIMEZONE;
    $showNext = isset($options['next']) ?? true;

    // Validate inputs
    if (!in_array($action, ['timings', 'calendar', 'hijricalendar', 'currentdate', 'currenttime', 'currenttimestamp', 'methods'])) {
        throw new InvalidArgumentException("Invalid action: $action");
    }

    if ($action === 'timings' && !preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
        throw new InvalidArgumentException("Invalid date format. Use DD-MM-YYYY");
    }

    if (($action === 'calendar' || $action === 'hijricalendar') && 
        (!is_numeric($year) || !is_numeric($month) || $month < 1 || $month > 12)) {
        throw new InvalidArgumentException("Invalid year or month");
    }

    switch ($action) {
        case 'timings':
            $response = $client->getTimings($date, $city, $country, $method);
            if (isset($response['data']['timings'])) {
                $prayerTimes = new PrayerTimes($response['data']['timings']);
                $prayerTimes->displayTimings(true, $showNext);
            } else {
                throw new RuntimeException("Error: Unable to fetch timings.");
            }
            break;

        case 'calendar':
        case 'hijricalendar':
            $method = $action === 'calendar' ? 'getCalendar' : 'getHijriCalendar';
            $response = $client->$method($year, $month, $city, $country, $method);
            if (isset($response['data'])) {
                displayCalendar($response['data']);
            } else {
                throw new RuntimeException("Error: Unable to fetch calendar.");
            }
            break;

        case 'currentdate':
        case 'currenttime':
        case 'currenttimestamp':
            $method = 'get' . ucfirst($action);
            $response = $client->$method($timezone);
            $label = ucwords(preg_replace('/([A-Z])/', ' $1', $action));
            echo "$label: " . ($response['data'] ?? "Unable to fetch $action") . "\n";
            break;

        case 'methods':
            $client->getMethods();
            break;
    }
} catch (InvalidArgumentException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
} catch (RuntimeException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
} catch (Exception $e) {
    fprintf(STDERR, "Unexpected error: %s\n", $e->getMessage());
    exit(1);
}
