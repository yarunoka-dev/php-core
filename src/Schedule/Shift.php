<?php

namespace Yarunoka\Schedule;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Internal\Vocabulary\Direction;

/**
 * The shift modifier — rounding. Takes each base day selected by the
 * `days` condition and moves it in a fixed direction until the landing
 * condition holds. orSame is the inclusive / exclusive distinction (the
 * same as java.time's previous / previousOrSame). Evaluation is done by
 * YrnkEvaluator.
 */
final readonly class Shift
{
    /**
     * @internal
     */
    public function __construct(
        /** @internal */
        public Direction $direction,
        /** @internal */
        public bool $orSame,
        /** @internal */
        public DayAtomInterface $condition,
    ) {
        if ($condition instanceof DayCycle) {
            throw new InvalidValueException('["every", N, "day"] is allowed only in the days enumeration (not as a shift landing condition)');
        }
    }
}
