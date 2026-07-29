<?php

namespace Yarunoka\Schedule;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Internal\Parser\Name;

/**
 * A name atom of the days axis — a reference to a date set. Which of the
 * two kinds the name is (an entry of calendar.date_sets, or one the host
 * binds to a resolver) makes no difference here: the reference is a
 * self-contained value, and that the referent exists is validated by the
 * holder of the definitions (YrnkParser / YrnkEvaluator).
 */
final readonly class DateSetRef implements DayAtomInterface
{
    /**
     * @internal
     */
    public function __construct(
        /** @internal */
        public string $name,
    ) {
        $problem = Name::problemWith($name);

        if ($problem !== null) {
            throw new InvalidValueException($problem);
        }
    }
}
