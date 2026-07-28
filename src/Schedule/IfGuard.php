<?php

namespace Yarunoka\Schedule;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Internal\Vocabulary\Direction;

/**
 * The if modifier — filtering by the base day itself or a neighbour.
 * shift moves the day; if filters without moving. A null direction means
 * "the day itself". Evaluation is done by YrnkEvaluator.
 */
final readonly class IfGuard
{
    /**
     * @internal
     */
    public function __construct(
        /** @internal */
        public ?Direction $direction,
        /** @internal */
        public bool $negated,
        /** @internal */
        public DayAtomInterface $condition,
    ) {
        if ($condition instanceof DayCycle) {
            throw new InvalidValueException('["every", N, "day"] is allowed only in the days enumeration (not as an if condition)');
        }
    }
}
