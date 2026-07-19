<?php

namespace Yarunoka\Vocabulary;

use Yarunoka\Exceptions\InvalidValueException;

/**
 * A day-of-week name. An atom of the DSL and, at the same time, the
 * representation of a date's day of week.
 */
enum DayName: string
{
    case Mon = 'mon';
    case Tue = 'tue';
    case Wed = 'wed';
    case Thu = 'thu';
    case Fri = 'fri';
    case Sat = 'sat';
    case Sun = 'sun';

    public static function fromIsoNumber(int $isoNumber): self
    {
        return match ($isoNumber) {
            1 => self::Mon,
            2 => self::Tue,
            3 => self::Wed,
            4 => self::Thu,
            5 => self::Fri,
            6 => self::Sat,
            7 => self::Sun,
            default => throw new InvalidValueException("ISO day-of-week number must be between 1 and 7: {$isoNumber}"),
        };
    }

    /**
     * The ISO day-of-week number paired with fromIsoNumber (Mon = 1 through
     * Sun = 7).
     */
    public function isoNumber(): int
    {
        return match ($this) {
            self::Mon => 1,
            self::Tue => 2,
            self::Wed => 3,
            self::Thu => 4,
            self::Fri => 5,
            self::Sat => 6,
            self::Sun => 7,
        };
    }

    public function isWeekend(): bool
    {
        return $this === self::Sat || $this === self::Sun;
    }
}
