<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

class PrayerTimesService
{
    private const BASE_URL = 'http://api.aladhan.com/v1/';

    private const DEFAULT_CITY = 'Manama';

    private const DEFAULT_COUNTRY = 'Bahrain';

    private const DEFAULT_METHOD = 10;

    public const DEFAULT_TIMEZONE = 'Asia/Bahrain';

    private const CACHE_DURATION = 86400; // 24 hours

    public const PRAYER_NAMES = ['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];

    private static array $validActions = [
        'timings', 'calendar', 'hijricalendar',
        'currentdate', 'currenttime', 'currenttimestamp',
        'methods', 'playadhan',
    ];

    /**
     * Validate that the action is supported.
     */
    public static function isValidAction(string $action): bool
    {
        return in_array($action, self::$validActions, true);
    }

    /**
     * Validate date format DD-MM-YYYY.
     */
    public static function validateDate(string $date): void
    {
        if (! preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
            throw new InvalidArgumentException('Invalid date format. Please use DD-MM-YYYY.');
        }
    }

    /**
     * Validate year/month for calendar endpoints.
     */
    public static function validateYearMonth(int|string $year, int|string $month): void
    {
        if (! is_numeric($year) || ! is_numeric($month) || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Invalid year or month.');
        }
    }

    public function getTimings(
        string $date,
        string $city = self::DEFAULT_CITY,
        string $country = self::DEFAULT_COUNTRY,
        int|string $method = self::DEFAULT_METHOD
    ): array {
        self::validateDate($date);

        return $this->fetch("timingsByCity/$date?city=$city&country=$country&method=$method");
    }

    public function getCalendar(
        int|string $year,
        int|string $month,
        string $city = self::DEFAULT_CITY,
        string $country = self::DEFAULT_COUNTRY,
        int|string $method = self::DEFAULT_METHOD
    ): array {
        self::validateYearMonth($year, $month);

        return $this->fetch("calendarByCity/$year/$month?city=$city&country=$country&method=$method");
    }

    public function getHijriCalendar(
        int|string $year,
        int|string $month,
        string $city = self::DEFAULT_CITY,
        string $country = self::DEFAULT_COUNTRY,
        int|string $method = self::DEFAULT_METHOD
    ): array {
        self::validateYearMonth($year, $month);

        return $this->fetch("hijriCalendarByCity/$year/$month?city=$city&country=$country&method=$method");
    }

    public function getCurrentDate(string $timezone = self::DEFAULT_TIMEZONE): array
    {
        return $this->fetch("currentDate?zone=$timezone");
    }

    public function getCurrentTime(string $timezone = self::DEFAULT_TIMEZONE): array
    {
        return $this->fetch("currentTime?zone=$timezone");
    }

    public function getCurrentTimestamp(string $timezone = self::DEFAULT_TIMEZONE): array
    {
        return $this->fetch("currentTimestamp?zone=$timezone");
    }

    public function getMethods(): array
    {
        return $this->fetch('methods');
    }

    /**
     * Fetch prayer timings for today with defaults (used by the daemon/check command).
     */
    public function getTodayTimings(
        string $city = self::DEFAULT_CITY,
        string $country = self::DEFAULT_COUNTRY,
        int|string $method = self::DEFAULT_METHOD
    ): array {
        $date = date('d-m-Y');
        $response = $this->getTimings($date, $city, $country, $method);

        if (! isset($response['data']['timings'])) {
            throw new RuntimeException('Could not fetch today\'s prayer timings.');
        }

        return $response['data']['timings'];
    }

    /**
     * Fetch from API with caching.
     */
    private function fetch(string $endpoint): array
    {
        $cacheKey = 'prayer_times_' . md5($endpoint);

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = $this->makeRequest($endpoint);

        Cache::put($cacheKey, $response, now()->addSeconds(self::CACHE_DURATION));

        return $response;
    }

    private function makeRequest(string $endpoint): array
    {
        $url = self::BASE_URL . $endpoint;

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
}
