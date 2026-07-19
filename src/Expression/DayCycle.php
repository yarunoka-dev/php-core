<?php

namespace Yarunoka\Expression;

use Yarunoka\Exceptions\InvalidValueException;

/**
 * The day-cycle tuple atom (["every", 2, "day"] — every N days). The
 * matching days count the date of the schedule's `from` as day one, so a
 * schedule that uses this atom requires `from` (an invariant of
 * YrnkSchedule). Allowed only as an element of the `days` enumeration (not
 * as a `shift` landing condition or an `if` condition).
 */
final readonly class DayCycle implements DayAtom
{
    public function __construct(public int $intervalDays)
    {
        if ($intervalDays < 1) {
            throw new InvalidValueException("Count of every must be an integer of at least 1: {$intervalDays}");
        }
    }
}
