<?php

namespace Yarunoka\Schedule;

use Yarunoka\Vocabulary\YrnkDayName;

/**
 * A day-of-week atom (every given weekday).
 */
final readonly class Weekday implements DayAtomInterface
{
    /**
     * @internal
     */
    public function __construct(
        /** @internal */
        public YrnkDayName $dayName,
    ) {}
}
