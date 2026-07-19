<?php

namespace Yarunoka\Time;

use Yarunoka\Exceptions\InvalidValueException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A calendar date-time without a timezone (a date plus a minute-precision
 * time). The from / until literal is "YYYY-MM-DD HH:MM" only (zero-padded,
 * a single space — U+0020 — no seconds).
 */
final readonly class LocalDateTime
{
    private function __construct(
        public LocalDate $date,
        public int $secondsFromMidnight,
    ) {}

    public static function fromString(string $dateTime): self
    {
        if (preg_match('/\A(\d{4}-\d{2}-\d{2}) ([01]\d|2[0-3]):([0-5]\d)\z/', $dateTime, $matches) !== 1) {
            throw new InvalidValueException("Date-time must be in \"YYYY-MM-DD HH:MM\" format: {$dateTime}");
        }

        return new self(
            LocalDate::fromString($matches[1]),
            (int) $matches[2] * 3600 + (int) $matches[3] * 60,
        );
    }

    /**
     * The "YYYY-MM-DD HH:MM" notation paired with fromString (used by the
     * builder).
     */
    public function toString(): string
    {
        return sprintf(
            '%s %02d:%02d',
            $this->date->toString(),
            intdiv($this->secondsFromMidnight, 3600),
            intdiv($this->secondsFromMidnight % 3600, 60),
        );
    }

    public function isBefore(self $other): bool
    {
        return ([$this->date->year, $this->date->month, $this->date->day, $this->secondsFromMidnight]
            <=> [$other->date->year, $other->date->month, $other->date->day, $other->secondsFromMidnight]) < 0;
    }

    /**
     * The instant in the given timezone. A wall time that does not exist
     * resolves by the same rule as scheduled points (LocalDate::atTime =
     * RFC 5545 §3.3.5).
     */
    public function toInstant(DateTimeZone $timezone): DateTimeImmutable
    {
        return $this->date->atTime($this->secondsFromMidnight, $timezone);
    }
}
