<?php

namespace Yarunoka\Internal\Builder;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Expression\CustomRef;
use Yarunoka\Expression\DayAtom;
use Yarunoka\Expression\DayCycle;
use Yarunoka\Expression\LastDayOfMonth;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\OrdinalWeekday;
use Yarunoka\Expression\Weekday;
use Yarunoka\Vocabulary\CalendarWord;

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
    public static function build(DayAtom $atom): int|string|array
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
