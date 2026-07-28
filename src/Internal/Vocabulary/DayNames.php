<?php

namespace Yarunoka\Internal\Vocabulary;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Vocabulary\DayName;

/**
 * Computations over the day-of-week vocabulary. They live here rather
 * than on the enum so that DayName stays the vocabulary itself — the
 * words a document is written in — while the arithmetic that reads a
 * date belongs to the engine.
 *
 * @internal
 */
final class DayNames
{
    public static function fromIsoNumber(int $isoNumber): DayName
    {
        return match ($isoNumber) {
            1 => DayName::Mon,
            2 => DayName::Tue,
            3 => DayName::Wed,
            4 => DayName::Thu,
            5 => DayName::Fri,
            6 => DayName::Sat,
            7 => DayName::Sun,
            default => throw new InvalidValueException("ISO day-of-week number must be between 1 and 7: {$isoNumber}"),
        };
    }

    /**
     * The ISO day-of-week number paired with fromIsoNumber (Mon = 1 through
     * Sun = 7).
     */
    public static function isoNumber(DayName $dayName): int
    {
        return match ($dayName) {
            DayName::Mon => 1,
            DayName::Tue => 2,
            DayName::Wed => 3,
            DayName::Thu => 4,
            DayName::Fri => 5,
            DayName::Sat => 6,
            DayName::Sun => 7,
        };
    }

    public static function isWeekend(DayName $dayName): bool
    {
        return $dayName === DayName::Sat || $dayName === DayName::Sun;
    }
}
