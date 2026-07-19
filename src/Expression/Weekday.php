<?php

namespace Yarunoka\Expression;

use Yarunoka\Vocabulary\DayName;

/**
 * A day-of-week atom (every given weekday).
 */
final readonly class Weekday implements DayAtom
{
    public function __construct(public DayName $dayName) {}
}
