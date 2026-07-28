<?php

namespace Yarunoka\Schedule;

use Yarunoka\Vocabulary\YrnkDayName;
use Yarunoka\Vocabulary\Ordinal;

/**
 * The ordinal-tuple atom (["3rd", "mon"] / ["last", "fri"] — the third
 * Monday / last Friday of the month).
 */
final readonly class OrdinalWeekday implements DayAtomInterface
{
    public function __construct(
        public Ordinal $ordinal,
        public YrnkDayName $dayName,
    ) {}
}
