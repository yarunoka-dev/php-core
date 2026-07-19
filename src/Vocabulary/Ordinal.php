<?php

namespace Yarunoka\Vocabulary;

/**
 * An ordinal word. Usable only as the first element of an ordinal tuple
 * ["3rd", "mon"].
 */
enum Ordinal: string
{
    case First = '1st';
    case Second = '2nd';
    case Third = '3rd';
    case Fourth = '4th';
    case Fifth = '5th';
    case Last = 'last';

    /**
     * Which week within the month. Last has no week number (it is matched
     * from the end of the month).
     */
    public function weekIndex(): ?int
    {
        return match ($this) {
            self::First => 1,
            self::Second => 2,
            self::Third => 3,
            self::Fourth => 4,
            self::Fifth => 5,
            self::Last => null,
        };
    }
}
