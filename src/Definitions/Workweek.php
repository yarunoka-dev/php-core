<?php

namespace Yarunoka\Definitions;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Vocabulary\DayName;

/**
 * The weekly pattern (the day-of-week set that sets the working default).
 * The bottom layer of the layer model. Left undefined (null on
 * Definitions), the default is Mon–Fri.
 */
final readonly class Workweek
{
    /** @var non-empty-list<DayName> */
    public array $days;

    /**
     * @param  list<DayName>  $days  Unvalidated input. Empty or duplicated enumerations violate the invariants
     */
    public function __construct(array $days)
    {
        if ($days === []) {
            throw new InvalidValueException('workweek cannot be empty');
        }

        if (count($days) !== count(array_unique(array_map(
            static fn (DayName $day): string => $day->value,
            $days,
        )))) {
            throw new InvalidValueException('Duplicate day name in workweek');
        }

        $this->days = $days;
    }
}
