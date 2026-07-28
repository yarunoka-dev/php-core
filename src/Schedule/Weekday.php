<?php

namespace Yarunoka\Schedule;

use Yarunoka\Vocabulary\YrnkDayName;

/**
 * A day-of-week atom (every given weekday).
 */
final readonly class Weekday implements DayAtomInterface
{
    public function __construct(public YrnkDayName $dayName) {}
}
