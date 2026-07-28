<?php

namespace Yarunoka\Expression;

use Yarunoka\Vocabulary\YrnkDayName;

/**
 * A day-of-week atom (every given weekday).
 */
final readonly class Weekday implements DayAtom
{
    public function __construct(public YrnkDayName $dayName) {}
}
