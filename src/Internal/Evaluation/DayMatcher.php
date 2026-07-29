<?php

namespace Yarunoka\Internal\Evaluation;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Schedule\DateSetRef;
use Yarunoka\Schedule\DayAtomInterface;
use Yarunoka\Schedule\LastDayOfMonth;
use Yarunoka\Schedule\MonthDay;
use Yarunoka\Schedule\OrdinalWeekday;
use Yarunoka\Schedule\Weekday;
use Yarunoka\Internal\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\YrnkDayName;
use Yarunoka\YrnkDate;

/**
 * The matcher for day expression atoms. The calendar vocabulary uses the
 * layer model (consulted top-down with early return):
 *
 *     business_days       top layer: "we work this day" — overrides everything below
 *     business_holidays   the organization's own closures
 *     holidays            public holidays; closed by default
 *     workweek            bottom layer: the weekly pattern that sets the default
 *
 * weekday / weekend ask the fixed calendar and consult no definition;
 * holiday asks the holidays list alone; business_day / business_holiday
 * are questions to the stacked conclusion.
 *
 * @internal
 */
final readonly class DayMatcher
{
    public function __construct(private ResolvedCalendar $calendar) {}

    public function matches(DayAtomInterface $atom, YrnkDate $date): bool
    {
        return match (true) {
            $atom instanceof MonthDay => self::dayOfMonth($date) === $atom->dayOfMonth,
            $atom instanceof Weekday => self::dayOfWeek($date) === $atom->dayName,
            $atom instanceof OrdinalWeekday => $this->matchesOrdinalWeekday($atom, $date),
            $atom instanceof LastDayOfMonth => self::dayOfMonth($date) === self::daysInMonth($date),
            $atom instanceof DateSetRef => $this->calendar->nameContains($atom->name, $date),
            $atom instanceof CalendarWord => $this->matchesCalendarWord($atom, $date),
            default => throw new InvalidValueException('Unknown day expression atom: ' . get_debug_type($atom)),
        };
    }

    private function matchesOrdinalWeekday(OrdinalWeekday $atom, YrnkDate $date): bool
    {
        if (self::dayOfWeek($date) !== $atom->dayName) {
            return false;
        }

        $weekIndex = $atom->ordinal->weekIndex();

        if ($weekIndex === null) {
            // last: the same weekday is 7 days later. If that does not fit
            // in the month, this one is the last.
            return self::dayOfMonth($date) + 7 > self::daysInMonth($date);
        }

        return intdiv(self::dayOfMonth($date) - 1, 7) + 1 === $weekIndex;
    }

    private function matchesCalendarWord(CalendarWord $word, YrnkDate $date): bool
    {
        return match ($word) {
            CalendarWord::Weekday => ! self::dayOfWeek($date)->isWeekend(),
            CalendarWord::Weekend => self::dayOfWeek($date)->isWeekend(),
            CalendarWord::Holiday => $this->calendar->holidayContains($date),
            CalendarWord::BusinessDay => $this->isBusinessDay($date),
            CalendarWord::BusinessHoliday => ! $this->isBusinessDay($date),
        };
    }

    private function isBusinessDay(YrnkDate $date): bool
    {
        if ($this->calendar->businessDayContains($date)) {
            return true;
        }

        if ($this->calendar->businessHolidayContains($date)) {
            return false;
        }

        if ($this->calendar->holidayContains($date)) {
            return false;
        }

        return $this->calendar->isInWorkweek(self::dayOfWeek($date));
    }

    private static function dayOfMonth(YrnkDate $date): int
    {
        return (int) $date->format('j');
    }

    private static function daysInMonth(YrnkDate $date): int
    {
        return (int) $date->format('t');
    }

    private static function dayOfWeek(YrnkDate $date): YrnkDayName
    {
        return YrnkDayName::fromIsoNumber((int) $date->format('N'));
    }
}
