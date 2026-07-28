<?php

namespace Yarunoka\Calendar;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Vocabulary\YrnkDayName;

/**
 * The weekly pattern (the day-of-week set that sets the working default).
 * The bottom layer of the layer model. Left undefined (null on
 * YrnkCalendar), the default is Mon–Fri.
 */
final readonly class YrnkWorkweek
{
    /** @var non-empty-list<YrnkDayName> */
    public array $days;

    /**
     * @param  list<YrnkDayName>  $days  Unvalidated input. Empty or duplicated enumerations violate the invariants
     */
    public function __construct(array $days)
    {
        if ($days === []) {
            throw new InvalidValueException('workweek cannot be empty');
        }

        if (count($days) !== count(array_unique(array_map(
            static fn(YrnkDayName $day): string => $day->value,
            $days,
        )))) {
            throw new InvalidValueException('Duplicate day name in workweek');
        }

        $this->days = $days;
    }
}
