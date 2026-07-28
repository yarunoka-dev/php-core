<?php

namespace Yarunoka\Internal\Builder;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Schedule\CustomRef;
use Yarunoka\Schedule\DayAtomInterface;
use Yarunoka\Schedule\DayCycle;
use Yarunoka\Schedule\LastDayOfMonth;
use Yarunoka\Schedule\MonthDay;
use Yarunoka\Schedule\OrdinalWeekday;
use Yarunoka\Schedule\Weekday;
use Yarunoka\Internal\Vocabulary\CalendarWord;

/**
 * The mirror image of DayAtomParser. Atom node → RawDayAtom.
 *
 * @internal
 */
final class DayAtomBuilder
{
    /**
     * @return int|string|list<int|string>
     */
    public static function build(DayAtomInterface $atom): int|string|array
    {
        return match (true) {
            $atom instanceof MonthDay => $atom->dayOfMonth,
            $atom instanceof DayCycle => ['every', $atom->intervalDays, 'day'],
            $atom instanceof Weekday => $atom->dayName->value,
            $atom instanceof CalendarWord => $atom->value,
            $atom instanceof OrdinalWeekday => [$atom->ordinal->value, $atom->dayName->value],
            $atom instanceof LastDayOfMonth => 'last_day_of_month',
            $atom instanceof CustomRef => $atom->name,
            default => throw new InvalidValueException('Unknown day expression atom: ' . get_debug_type($atom)),
        };
    }
}
