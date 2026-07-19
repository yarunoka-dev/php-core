<?php

namespace Yarunoka\Internal\Evaluation;

use Yarunoka\Expression\DayAtom;
use Yarunoka\Expression\LastDayOfMonth;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\OrdinalWeekday;
use Yarunoka\Expression\Weekday;
use Yarunoka\Time\LocalDate;
use Yarunoka\Vocabulary\DayName;

/**
 * Atom × (year, month) → the enumeration of matching days of that month
 * (day numbers). Atoms determined by the structure of the calendar are
 * computed directly by arithmetic; atoms backed by definition data
 * (custom references, calendar vocabulary) are picked by running the days
 * of the month through the DayMatcher. The DayMatcher is the single
 * authority on membership semantics; this is its enumerating form.
 *
 * @internal
 */
final readonly class AtomDayEnumerator
{
    public function __construct(private DayMatcher $dayMatcher) {}

    /**
     * @return list<int> Day numbers in ascending order
     */
    public function daysIn(DayAtom $atom, int $year, int $month): array
    {
        return match (true) {
            $atom instanceof MonthDay => $atom->dayOfMonth <= $this->daysInMonth($year, $month)
                ? [$atom->dayOfMonth]
                : [],
            $atom instanceof Weekday => $this->weekdayDays($atom->dayName, $year, $month),
            $atom instanceof OrdinalWeekday => $this->ordinalWeekdayDays($atom, $year, $month),
            $atom instanceof LastDayOfMonth => [$this->daysInMonth($year, $month)],
            default => $this->scanDays($atom, $year, $month),
        };
    }

    /**
     * @return list<int>
     */
    private function weekdayDays(DayName $dayName, int $year, int $month): array
    {
        $first = LocalDate::of($year, $month, 1);
        $offset = ($dayName->isoNumber() - $first->dayOfWeek()->isoNumber() + 7) % 7;
        $days = [];

        for ($day = 1 + $offset; $day <= $first->daysInMonth(); $day += 7) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * @return list<int>
     */
    private function ordinalWeekdayDays(OrdinalWeekday $atom, int $year, int $month): array
    {
        $days = $this->weekdayDays($atom->dayName, $year, $month);
        $weekIndex = $atom->ordinal->weekIndex();

        if ($weekIndex === null) {
            return array_slice($days, -1);
        }

        return isset($days[$weekIndex - 1]) ? [$days[$weekIndex - 1]] : [];
    }

    /**
     * @return list<int>
     */
    private function scanDays(DayAtom $atom, int $year, int $month): array
    {
        $days = [];
        $daysInMonth = $this->daysInMonth($year, $month);

        for ($day = 1; $day <= $daysInMonth; $day++) {
            if ($this->dayMatcher->matches($atom, LocalDate::of($year, $month, $day))) {
                $days[] = $day;
            }
        }

        return $days;
    }

    private function daysInMonth(int $year, int $month): int
    {
        return LocalDate::of($year, $month, 1)->daysInMonth();
    }
}
