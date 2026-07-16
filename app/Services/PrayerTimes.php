<?php

namespace App\Services;

use DateInterval;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;

class PrayerTimes
{
    private array $timings;

    private ?string $nextPrayerName = null;

    private ?DateTime $nextPrayerDateTime = null;

    private string $timezone;

    public function __construct(array $timings, string $timezone = PrayerTimesService::DEFAULT_TIMEZONE)
    {
        $this->validateTimings($timings);
        $this->timings = $timings;
        $this->timezone = $timezone;
        $this->calculateNextPrayer();
    }

    public function getTimings(): array
    {
        return $this->timings;
    }

    public function getNextPrayerName(): ?string
    {
        return $this->nextPrayerName;
    }

    public function getNextPrayerDateTime(): ?DateTime
    {
        return $this->nextPrayerDateTime;
    }

    /**
     * Get the time until the next prayer as a formatted string.
     */
    public function getNextPrayerCountdown(): ?string
    {
        if ($this->nextPrayerDateTime === null) {
            return null;
        }

        $tz = new DateTimeZone($this->timezone);
        $now = new DateTime('now', $tz);
        $diff = $now->diff($this->nextPrayerDateTime);

        return $this->formatTimeDiff($diff);
    }

    /**
     * Check if a specific prayer is happening now (within a grace window).
     *
     * @param  string   $prayerName
     * @param  int      $graceMinutes Window in minutes before/after the exact time
     * @return bool
     */
    public function isPrayerTime(string $prayerName, int $graceMinutes = 2): bool
    {
        if (! isset($this->timings[$prayerName])) {
            return false;
        }

        $tz = new DateTimeZone($this->timezone);
        $now = new DateTime('now', $tz);
        $timeStr = explode(' ', $this->timings[$prayerName])[0];
        $prayerTime = DateTime::createFromFormat('H:i', $timeStr, $tz);

        if (! $prayerTime) {
            return false;
        }

        $diff = abs($now->getTimestamp() - $prayerTime->getTimestamp());

        return $diff <= $graceMinutes * 60;
    }

    /**
     * Get the time until the next prayer as seconds (for sleep/interval).
     */
    public function getSecondsUntilNextPrayer(): ?int
    {
        if ($this->nextPrayerDateTime === null) {
            return null;
        }

        $tz = new DateTimeZone($this->timezone);
        $now = new DateTime('now', $tz);

        return max(0, $this->nextPrayerDateTime->getTimestamp() - $now->getTimestamp());
    }

    /**
     * Display timings to stdout with optional highlighting.
     */
    public function displayTimings(bool $highlightNext = true, bool $showNext = true): void
    {
        foreach (PrayerTimesService::PRAYER_NAMES as $prayer) {
            $line = str_pad($prayer, 10) . ': ' . $this->timings[$prayer];
            if ($highlightNext && $prayer === $this->nextPrayerName) {
                echo "\033[1;32m" . $line . "  (Next)\033[0m\n";
            } else {
                echo $line . "\n";
            }
        }

        if ($showNext && $this->nextPrayerName !== null && $this->nextPrayerDateTime !== null) {
            $tz = new DateTimeZone($this->timezone);
            $diff = (new DateTime('now', $tz))->diff($this->nextPrayerDateTime);
            $human = $this->formatTimeDiff($diff);

            echo "\n";
            echo "Next prayer : \033[1;32m{$this->nextPrayerName}\033[0m in $human\n";
        }
    }

    private function validateTimings(array $timings): void
    {
        foreach (PrayerTimesService::PRAYER_NAMES as $prayer) {
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
        $tz = new DateTimeZone($this->timezone);
        $now = new DateTime('now', $tz);

        foreach (PrayerTimesService::PRAYER_NAMES as $prayer) {
            $timeStr = explode(' ', $this->timings[$prayer])[0];
            $prayerTime = DateTime::createFromFormat('H:i', $timeStr, $tz);

            if ($prayerTime < $now) {
                $prayerTime->modify('+1 day');
            }

            if ($this->nextPrayerDateTime === null || $prayerTime < $this->nextPrayerDateTime) {
                $this->nextPrayerName = $prayer;
                $this->nextPrayerDateTime = $prayerTime;
            }
        }
    }

    private function formatTimeDiff(DateInterval $interval): string
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
