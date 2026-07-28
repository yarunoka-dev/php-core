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
    /**
     * @internal
     */
    public function __construct(
        /** @internal */
        public Ordinal $ordinal,
        /** @internal */
        public YrnkDayName $dayName,
    ) {}
}
