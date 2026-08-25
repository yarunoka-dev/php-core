<?php

namespace Yarunoka\Schedule;

use Yarunoka\Exceptions\InvalidValueException;

/**
 * The day-cycle tuple atom (["every", 2, "day"] — every N days). The
 * matching days count the date of the schedule's `from` as day one, so a
 * schedule that uses this atom requires `from` (an invariant of
 * YrnkSchedule). Allowed only as an element of the `days` enumeration (not
 * as a `shift` landing condition or an `if` condition).
 */
final readonly class DayCycle implements DayAtomInterface
{
    /**
     * The largest count whose second matching day stays inside the date
     * domain when `from` sits at its lower end. A count beyond it makes
     * a document declaring 1.1 invalid; 1.0 documents keep their
     * unbounded counts, which the closed date domain answers with the
     * `from` day alone (validated by Yrnk, where the declared version
     * lives).
     */
    public const int MAX_COUNT = 3_652_058;

    /**
     * @internal
     */
    public function __construct(
        /** @internal */
        public int $intervalDays,
    ) {
        if ($intervalDays < 1) {
            throw new InvalidValueException("Count of every must be an integer of at least 1: {$intervalDays}");
        }
    }
}
