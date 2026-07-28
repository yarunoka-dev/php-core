<?php

namespace Yarunoka\Internal\Vocabulary;

use Yarunoka\Vocabulary\Ordinal;

/**
 * Computations over the ordinal vocabulary.
 *
 * @internal
 */
final class Ordinals
{
    /**
     * Which week within the month. Last has no week number (it is matched
     * from the end of the month).
     */
    public static function weekIndex(Ordinal $ordinal): ?int
    {
        return match ($ordinal) {
            Ordinal::First => 1,
            Ordinal::Second => 2,
            Ordinal::Third => 3,
            Ordinal::Fourth => 4,
            Ordinal::Fifth => 5,
            Ordinal::Last => null,
        };
    }
}
