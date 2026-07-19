<?php

namespace Yarunoka\Expression;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Time\TimeOfDay;

/**
 * An enumeration of fixed times. Kept in written order so that
 * round-tripping is the identity (sorting is evaluation's job).
 */
final readonly class FixedTimes implements TimesSpec
{
    /** @var non-empty-list<TimeOfDay> */
    public array $times;

    /**
     * @param  list<TimeOfDay>  $times  Unvalidated input. Empty or duplicated enumerations violate the invariants
     */
    public function __construct(array $times)
    {
        if ($times === []) {
            throw new InvalidValueException('Times enumeration cannot be empty');
        }

        $seen = [];

        foreach ($times as $time) {
            if (isset($seen[$time->secondsFromMidnight])) {
                throw new InvalidValueException('Duplicate time in times');
            }

            $seen[$time->secondsFromMidnight] = true;
        }

        $this->times = $times;
    }
}
