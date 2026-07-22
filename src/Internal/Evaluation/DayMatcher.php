<?php

namespace Yarunoka\Internal\Evaluation;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Expression\CustomRef;
use Yarunoka\Expression\DayAtom;
use Yarunoka\Expression\LastDayOfMonth;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\OrdinalWeekday;
use Yarunoka\Expression\Weekday;
use Yarunoka\Time\LocalDate;
use Yarunoka\Vocabulary\CalendarWord;

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

    public function matches(DayAtom $atom, LocalDate $date): bool
    {
        return match (true) {
            $atom instanceof MonthDay => $date->day === $atom->dayOfMonth,
            $atom instanceof Weekday => $date->dayOfWeek() === $atom->dayName,
            $atom instanceof OrdinalWeekday => $this->matchesOrdinalWeekday($atom, $date),
            $atom instanceof LastDayOfMonth => $date->day === $date->daysInMonth(),
            $atom instanceof CustomRef => $this->calendar->customContains($atom->name, $date),
            $atom instanceof CalendarWord => $this->matchesCalendarWord($atom, $date),
            default => throw new InvalidValueException('Unknown day expression atom: ' . get_debug_type($atom)),
        };
    }

    private function matchesOrdinalWeekday(OrdinalWeekday $atom, LocalDate $date): bool
    {
        if ($date->dayOfWeek() !== $atom->dayName) {
            return false;
        }

        $weekIndex = $atom->ordinal->weekIndex();

        if ($weekIndex === null) {
            // last: the same weekday is 7 days later. If that does not fit
            // in the month, this one is the last.
            return $date->day + 7 > $date->daysInMonth();
        }

        return intdiv($date->day - 1, 7) + 1 === $weekIndex;
    }

    private function matchesCalendarWord(CalendarWord $word, LocalDate $date): bool
    {
        return match ($word) {
            CalendarWord::Weekday => ! $date->dayOfWeek()->isWeekend(),
            CalendarWord::Weekend => $date->dayOfWeek()->isWeekend(),
            CalendarWord::Holiday => $this->calendar->holidayContains($date),
            CalendarWord::BusinessDay => $this->isBusinessDay($date),
            CalendarWord::BusinessHoliday => ! $this->isBusinessDay($date),
        };
    }

    private function isBusinessDay(LocalDate $date): bool
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

        return $this->calendar->isInWorkweek($date->dayOfWeek());
    }
}
