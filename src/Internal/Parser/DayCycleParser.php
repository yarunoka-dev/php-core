<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\DayCycle;

/**
 * The parser for the day-cycle tuple (["every", 2, "day"] — every N
 * days). Only arrays whose first element is "every" arrive here (the
 * routing is done by DayExpressionParser). The unit is explicit and fixed
 * to "day" — as with the times every, no sometimes-written,
 * sometimes-not asymmetry is created.
 *
 * @internal
 */
final class DayCycleParser
{
    /**
     * @param  array<mixed>  $raw
     */
    public static function parse(array $raw): DayCycle
    {
        if (! array_is_list($raw) || count($raw) !== 3) {
            throw new InvalidYrnkException('A day-cycle tuple must be the three elements ["every", count, "day"]');
        }

        [, $amount, $unitWord] = $raw;

        if (! is_int($amount) || $amount < 1) {
            $given = is_int($amount) ? (string) $amount : get_debug_type($amount);

            throw new InvalidYrnkException("Count of every must be an integer of at least 1: {$given}");
        }

        if ($unitWord !== 'day') {
            $given = is_string($unitWord) ? $unitWord : get_debug_type($unitWord);

            throw new InvalidYrnkException("The unit of the date-axis every is \"day\" (singular) only: {$given}");
        }

        return new DayCycle($amount);
    }
}
