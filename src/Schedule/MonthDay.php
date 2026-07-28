<?php

namespace Yarunoka\Schedule;

use Yarunoka\Exceptions\InvalidValueException;

/**
 * A day-of-month atom (the nth day of every month).
 */
final readonly class MonthDay implements DayAtomInterface
{
    /**
     * @internal
     */
    public function __construct(
        /** @internal */
        public int $dayOfMonth,
    ) {
        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            throw new InvalidValueException("Day of month must be between 1 and 31: {$dayOfMonth}");
        }
    }
}
