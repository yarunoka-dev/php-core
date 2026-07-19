<?php

namespace Yarunoka\Time;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Vocabulary\DayName;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * A single calendar day without a timezone. All day matching for schedules
 * is done against this "wall calendar day".
 */
final readonly class LocalDate
{
    private function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {}

    public static function of(int $year, int $month, int $day): self
    {
        if (! checkdate($month, $day, $year)) {
            throw new InvalidValueException(sprintf('Date does not exist: %04d-%02d-%02d', $year, $month, $day));
        }

        return new self($year, $month, $day);
    }

    /**
     * Accepts zero-padded YYYY-MM-DD only.
     */
    public static function fromString(string $date): self
    {
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $matches) !== 1) {
            throw new InvalidValueException("Date must be in YYYY-MM-DD format: {$date}");
        }

        return self::of((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    /**
     * Extracts the wall date in the DateTime's own timezone.
     */
    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return new self(
            (int) $dateTime->format('Y'),
            (int) $dateTime->format('n'),
            (int) $dateTime->format('j'),
        );
    }

    public function dayOfWeek(): DayName
    {
        return DayName::fromIsoNumber((int) $this->toDateTime()->format('N'));
    }

    public function daysInMonth(): int
    {
        return (int) $this->toDateTime()->format('t');
    }

    public function addDays(int $days): self
    {
        return self::fromDateTime($this->toDateTime()->modify(sprintf('%+d days', $days)));
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year
            && $this->month === $other->month
            && $this->day === $other->day;
    }

    /**
     * The number of days from this day to $other (signed; negative when
     * $other is in the past).
     */
    public function daysUntil(self $other): int
    {
        return intdiv($other->toDateTime()->getTimestamp() - $this->toDateTime()->getTimestamp(), 86400);
    }

    public function isAfter(self $other): bool
    {
        return ([$this->year, $this->month, $this->day] <=> [$other->year, $other->month, $other->day]) > 0;
    }

    public function toString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    /**
     * The instant $secondsFromMidnight past this day's midnight, in the
     * given timezone.
     */
    public function atTime(int $secondsFromMidnight, DateTimeZone $timezone): DateTimeImmutable
    {
        $hours = intdiv($secondsFromMidnight, 3600);
        $minutes = intdiv($secondsFromMidnight % 3600, 60);
        $seconds = $secondsFromMidnight % 60;

        return new DateTimeImmutable(
            sprintf('%s %02d:%02d:%02d', $this->toString(), $hours, $minutes, $seconds),
            $timezone,
        );
    }

    private function toDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->toString(), new DateTimeZone('UTC'));
    }
}
