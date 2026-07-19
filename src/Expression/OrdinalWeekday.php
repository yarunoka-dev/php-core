<?php

namespace Yarunoka\Expression;

use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Ordinal;

/**
 * The ordinal-tuple atom (["3rd", "mon"] / ["last", "fri"] — the third
 * Monday / last Friday of the month).
 */
final readonly class OrdinalWeekday implements DayAtom
{
    public function __construct(
        public Ordinal $ordinal,
        public DayName $dayName,
    ) {}
}
