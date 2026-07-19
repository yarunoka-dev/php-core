<?php

namespace Yarunoka\Internal\Evaluation;

use Yarunoka\Expression\AllDay;
use Yarunoka\Expression\DayCycle;
use Yarunoka\Expression\EverySequence;
use Yarunoka\Expression\IfGuard;
use Yarunoka\Expression\Shift;
use Yarunoka\Time\LocalDate;
use Yarunoka\Time\LocalDateTime;
use Yarunoka\Vocabulary\Direction;
use Yarunoka\YrnkSchedule;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Enumeration of candidate months and composition of if / shift / times
 * (the substance of matches / hasMatchIn).
 *
 * Interval questions are evaluated by the year → month → day hierarchy,
 * not by walking days: years / months narrow the (year, month) pairs
 * overlapping the interval, the days atoms enumerate the matching days
 * per month, if filters them, shift maps them to landing days, and the
 * times points are laid on top and checked against the interval. The
 * answer becomes no when the candidates run out, so there is no search
 * horizon.
 *
 * from / until (the validity range) folds into the question interval as
 * the boundary that clips the set of points to [from, until). The
 * interval every (EverySequence) is a sequence of points that uses no
 * matching-days × times product, so it is evaluated on a dedicated
 * arithmetic path that skips the day hierarchy. The day cycle (DayCycle)
 * counts from the schedule's from, so it is matched here rather than in
 * the context-free DayMatcher.
 *
 * @internal
 */
final readonly class MatchFinder
{
    /**
     * Definition data in which no day satisfying the shift landing
     * condition appears within this many consecutive days is considered a
     * contract violation: the search is cut off and the answer is "no
     * landing".
     */
    private const int SHIFT_SEARCH_LIMIT_DAYS = 366;

    public function __construct(
        private DayMatcher $dayMatcher,
        private AtomDayEnumerator $enumerator,
        private TimesExpander $expander,
        private DateTimeZone $timezone,
    ) {}

    /**
     * Does this date-time match? Times are truncated to whole seconds for
     * comparison (the DSL's scheduled points are never finer than a
     * second). allday matches on the day alone and ignores the time, but
     * the from / until clipping applies to its point (the start of the
     * day, 00:00).
     */
    public function matches(YrnkSchedule $schedule, DateTimeImmutable $at): bool
    {
        if ($schedule->times instanceof EverySequence) {
            // Points are whole seconds, so "is there a point in
            // (at−1 second, at]" = "is at a point".
            return $this->hasMatchIn($schedule, $at->modify('-1 second'), $at);
        }

        $day = LocalDate::fromDateTime($at->setTimezone($this->timezone));

        if (! $this->dayMatches($schedule, $day)) {
            return false;
        }

        if ($schedule->times instanceof AllDay) {
            return $this->withinRange($schedule, $day->atTime(0, $this->timezone));
        }

        // Compare against the day's points resolved to instants, not
        // against wall-clock seconds. On a DST transition day the wall
        // clock and the points diverge (a nonexistent time is pushed
        // forward, a time that occurs twice counts only as its first
        // occurrence — RFC 5545 §3.3.5), so point generation is aligned
        // with hasMatchIn via the same atTime.
        $timestamp = $at->getTimestamp();

        foreach ($this->expander->secondsOf($schedule->times) as $second) {
            $instant = $day->atTime($second, $this->timezone);

            if ($instant->getTimestamp() === $timestamp) {
                return $this->withinRange($schedule, $instant);
            }
        }

        return false;
    }

    /**
     * Is there a matching date-time in the half-open interval (from, to]?
     */
    public function hasMatchIn(YrnkSchedule $schedule, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        // Fold the validity range [valid-from, until) into the question
        // interval (from, to]. Points are whole seconds, so
        // t >= valid-from ⇔ t > valid-from − 1s, and t < until ⇔
        // t <= until − 1s.
        if ($schedule->from !== null) {
            $lower = $schedule->from->toInstant($this->timezone)->modify('-1 second');

            if ($lower > $from) {
                $from = $lower;
            }
        }

        if ($schedule->until !== null) {
            $upper = $schedule->until->toInstant($this->timezone)->modify('-1 second');

            if ($upper < $to) {
                $to = $upper;
            }
        }

        if ($from >= $to) {
            return false;
        }

        if ($schedule->times instanceof EverySequence) {
            return $this->sequenceHasMatchIn($schedule->from, $schedule->times, $from, $to);
        }

        $seconds = $this->expander->secondsOf($schedule->times);

        if ($seconds === []) {
            return false;
        }

        $fromDay = LocalDate::fromDateTime($from->setTimezone($this->timezone));
        $toDay = LocalDate::fromDateTime($to->setTimezone($this->timezone));

        // Only landing days (base days, without a shift) inside
        // [fromDay, toDay] can reach the interval, so look at the base
        // days of the overlapping months first.
        for ($index = self::monthIndex($fromDay); $index <= self::monthIndex($toDay); $index++) {
            [$year, $month] = self::yearMonthAt($index);

            if ($this->hasInstantIn($this->landedDaysIn($schedule, $year, $month), $seconds, $from, $to, $fromDay, $toDay)) {
                return true;
            }
        }

        if ($schedule->shift === null) {
            return false;
        }

        // Base days in months outside the interval can be shifted into
        // it; walk the months on the side opposite to the shift direction
        // to pick those up.
        return $schedule->shift->direction === Direction::Next
            ? $this->hasSpilledMatchBefore($schedule, $seconds, $from, $to, $fromDay, $toDay)
            : $this->hasSpilledMatchAfter($schedule, $seconds, $from, $to, $fromDay, $toDay);
    }

    /**
     * Base days of earlier months spilling into the interval by a
     * forward (next) shift. A landing is at most 366 days from its base
     * day (the contract), so months further back are cut off. Landing
     * days are monotonic in their base days, so the search is exhausted
     * once the month's last landing falls before from.
     *
     * @param  list<int>  $seconds
     */
    private function hasSpilledMatchBefore(
        YrnkSchedule $schedule,
        array $seconds,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        LocalDate $fromDay,
        LocalDate $toDay,
    ): bool {
        for ($index = self::monthIndex($fromDay) - 1; ; $index--) {
            [$year, $month] = self::yearMonthAt($index);
            $monthLast = LocalDate::of($year, $month, LocalDate::of($year, $month, 1)->daysInMonth());

            if ($fromDay->isAfter($monthLast->addDays(self::SHIFT_SEARCH_LIMIT_DAYS))) {
                return false;
            }

            $landed = $this->landedDaysIn($schedule, $year, $month);

            if ($this->hasInstantIn($landed, $seconds, $from, $to, $fromDay, $toDay)) {
                return true;
            }

            if ($landed !== [] && $fromDay->isAfter($landed[array_key_last($landed)])) {
                return false;
            }
        }
    }

    /**
     * Base days of later months spilling into the interval by a backward
     * (prev) shift. The cutoff is the mirror image of
     * hasSpilledMatchBefore.
     *
     * @param  list<int>  $seconds
     */
    private function hasSpilledMatchAfter(
        YrnkSchedule $schedule,
        array $seconds,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        LocalDate $fromDay,
        LocalDate $toDay,
    ): bool {
        for ($index = self::monthIndex($toDay) + 1; ; $index++) {
            [$year, $month] = self::yearMonthAt($index);
            $monthFirst = LocalDate::of($year, $month, 1);

            if ($monthFirst->addDays(-self::SHIFT_SEARCH_LIMIT_DAYS)->isAfter($toDay)) {
                return false;
            }

            $landed = $this->landedDaysIn($schedule, $year, $month);

            if ($this->hasInstantIn($landed, $seconds, $from, $to, $fromDay, $toDay)) {
                return true;
            }

            if ($landed !== [] && $landed[0]->isAfter($toDay)) {
                return false;
            }
        }
    }

    /**
     * The base days of the month (the AND of years / months / days,
     * filtered by if) mapped to their shift landing days, in ascending
     * order. Consecutive base days collapse into the same landing day.
     *
     * @return list<LocalDate>
     */
    private function landedDaysIn(YrnkSchedule $schedule, int $year, int $month): array
    {
        if ($schedule->years !== null && ! in_array($year, $schedule->years, true)) {
            return [];
        }

        if ($schedule->months !== null && ! in_array($month, $schedule->months, true)) {
            return [];
        }

        $dayNumbers = $schedule->days === null
            ? range(1, LocalDate::of($year, $month, 1)->daysInMonth())
            : $this->enumerateAtomDays($schedule, $year, $month);

        $landed = [];

        foreach ($dayNumbers as $dayNumber) {
            $base = LocalDate::of($year, $month, $dayNumber);

            if (! $this->passesIf($schedule->if, $base)) {
                continue;
            }

            $day = $schedule->shift === null ? $base : $this->landingOf($schedule->shift, $base);

            if ($day === null) {
                continue;
            }

            if ($landed !== [] && $landed[array_key_last($landed)]->equals($day)) {
                continue;
            }

            $landed[] = $day;
        }

        return $landed;
    }

    /**
     * The union of the atom enumerations (OR). Only the day cycle counts
     * from the schedule's from, so it is enumerated here; everything else
     * is delegated to the AtomDayEnumerator.
     *
     * @return list<int> Day numbers, ascending and without duplicates
     */
    private function enumerateAtomDays(YrnkSchedule $schedule, int $year, int $month): array
    {
        $seen = [];

        foreach ($schedule->days->atoms ?? [] as $atom) {
            $days = $atom instanceof DayCycle
                ? $this->cycleDaysIn($schedule, $atom, $year, $month)
                : $this->enumerator->daysIn($atom, $year, $month);

            foreach ($days as $day) {
                $seen[$day] = true;
            }
        }

        $days = array_keys($seen);
        sort($days);

        return $days;
    }

    /**
     * The day cycle's matching day numbers (that month's part). Every Nth
     * day counting the from date as day one — the first matching day is
     * computed arithmetically from the day difference between the first
     * of the month and from.
     *
     * @return list<int>
     */
    private function cycleDaysIn(YrnkSchedule $schedule, DayCycle $atom, int $year, int $month): array
    {
        /** @var LocalDateTime $anchor The YrnkSchedule invariant requires from for vocabulary that counts */
        $anchor = $schedule->from;
        $first = LocalDate::of($year, $month, 1);
        $offset = $anchor->date->daysUntil($first);

        if ($offset <= 0) {
            // The from day is on or after the first of the month (the
            // count starts in this month or later).
            $startDay = 1 - $offset;
        } else {
            $remainder = $offset % $atom->intervalDays;
            $startDay = 1 + ($remainder === 0 ? 0 : $atom->intervalDays - $remainder);
        }

        $days = [];

        for ($day = $startDay; $day <= $first->daysInMonth(); $day += $atom->intervalDays) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * Among the landing days overlapping the interval, is there a time
     * point after from and at or before to? Days strictly inside fromDay
     * and toDay always have their time points inside the interval (points
     * exist only within the day, [00:00, 24:00)), so only the boundary
     * days need their times checked.
     *
     * @param  list<LocalDate>  $days
     * @param  list<int>  $seconds
     */
    private function hasInstantIn(
        array $days,
        array $seconds,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        LocalDate $fromDay,
        LocalDate $toDay,
    ): bool {
        foreach ($days as $day) {
            if ($fromDay->isAfter($day) || $day->isAfter($toDay)) {
                continue;
            }

            if (! $day->equals($fromDay) && ! $day->equals($toDay)) {
                return true;
            }

            foreach ($seconds as $second) {
                $instant = $day->atTime($second, $this->timezone);

                if ($instant > $from && $instant <= $to) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The single-day decision (the day part of matches). With a shift,
     * "is there a base day that lands on this day" is checked by walking
     * the candidates opposite to the shift direction and verifying with
     * the forward landing computation.
     */
    private function dayMatches(YrnkSchedule $schedule, LocalDate $date): bool
    {
        if ($schedule->shift === null) {
            return $this->isBaseDay($schedule, $date);
        }

        // A landing day always satisfies the landing condition.
        if (! $this->dayMatcher->matches($schedule->shift->condition, $date)) {
            return false;
        }

        // Candidate base days run from date, opposite to the shift
        // direction, up to the next landing-condition day after date
        // (base days beyond it fall there or further).
        $step = -$schedule->shift->direction->step();
        $cursor = $date;

        for ($i = 0; $i <= self::SHIFT_SEARCH_LIMIT_DAYS; $i++) {
            if ($this->isBaseDay($schedule, $cursor) && $this->landsOn($schedule->shift, $cursor, $date)) {
                return true;
            }

            $cursor = $cursor->addDays($step);

            if ($this->dayMatcher->matches($schedule->shift->condition, $cursor)) {
                // For a strict shift (without or_same), this
                // landing-condition day itself is the last candidate that
                // can fall on date.
                return ! $schedule->shift->orSame
                    && $this->isBaseDay($schedule, $cursor)
                    && $this->landsOn($schedule->shift, $cursor, $date);
            }
        }

        return false;
    }

    private function isBaseDay(YrnkSchedule $schedule, LocalDate $date): bool
    {
        if ($schedule->years !== null && ! in_array($date->year, $schedule->years, true)) {
            return false;
        }

        if ($schedule->months !== null && ! in_array($date->month, $schedule->months, true)) {
            return false;
        }

        if ($schedule->days !== null && ! $this->matchesAnyAtom($schedule, $date)) {
            return false;
        }

        return $this->passesIf($schedule->if, $date);
    }

    private function matchesAnyAtom(YrnkSchedule $schedule, LocalDate $date): bool
    {
        foreach ($schedule->days->atoms ?? [] as $atom) {
            $matched = $atom instanceof DayCycle
                ? $this->matchesCycle($schedule, $atom, $date)
                : $this->dayMatcher->matches($atom, $date);

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    /**
     * The day cycle decision. Only days on or after the from date, a
     * multiple of N days away from it, match.
     */
    private function matchesCycle(YrnkSchedule $schedule, DayCycle $atom, LocalDate $date): bool
    {
        /** @var LocalDateTime $anchor The YrnkSchedule invariant requires from for vocabulary that counts */
        $anchor = $schedule->from;
        $offset = $anchor->date->daysUntil($date);

        return $offset >= 0 && $offset % $atom->intervalDays === 0;
    }

    /**
     * if filters without moving the day. shift then moves the filtered
     * result as base days.
     */
    private function passesIf(?IfGuard $if, LocalDate $date): bool
    {
        if ($if === null) {
            return true;
        }

        $target = $if->direction === null ? $date : $date->addDays($if->direction->step());
        $result = $this->dayMatcher->matches($if->condition, $target);

        return $if->negated ? ! $result : $result;
    }

    private function landsOn(Shift $shift, LocalDate $base, LocalDate $target): bool
    {
        $landing = $this->landingOf($shift, $base);

        return $landing !== null && $landing->equals($target);
    }

    /**
     * The landing day of a base day (the forward landing computation).
     * Walks in the given direction until the landing condition holds.
     * or_same includes the base day itself; the strict form advances one
     * day before searching.
     */
    private function landingOf(Shift $shift, LocalDate $base): ?LocalDate
    {
        $cursor = $shift->orSame ? $base : $base->addDays($shift->direction->step());

        for ($i = 0; $i <= self::SHIFT_SEARCH_LIMIT_DAYS; $i++) {
            if ($this->dayMatcher->matches($shift->condition, $cursor)) {
                return $cursor;
            }

            $cursor = $cursor->addDays($shift->direction->step());
        }

        return null;
    }

    /**
     * Is the point inside the validity range [from, until)?
     */
    private function withinRange(YrnkSchedule $schedule, DateTimeImmutable $instant): bool
    {
        if ($schedule->from !== null && $instant < $schedule->from->toInstant($this->timezone)) {
            return false;
        }

        if ($schedule->until !== null && $instant >= $schedule->until->toInstant($this->timezone)) {
            return false;
        }

        return true;
    }

    /**
     * Is there a point of the interval sequence (from + k × interval) in
     * (from, to]? The sequence is monotonically non-decreasing by
     * wall-clock arithmetic, so binary-search the first point after from
     * and check it is at or before to.
     *
     * @param  ?LocalDateTime  $anchor  Never null: the YrnkSchedule invariant requires from for vocabulary that counts
     */
    private function sequenceHasMatchIn(
        ?LocalDateTime $anchor,
        EverySequence $sequence,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): bool {
        if ($anchor === null) {
            return false;
        }

        $step = $sequence->stepSeconds();

        if ($this->sequenceInstant($anchor, 0) > $from) {
            return $this->sequenceInstant($anchor, 0) <= $to;
        }

        // Upper bound: with the wall-clock seconds elapsed up to from
        // plus two days of slack (wall clock and real time diverge by at
        // most a few hours even across DST), that point is guaranteed to
        // be after from.
        $fromWall = $from->setTimezone($this->timezone);
        $elapsedWall = $anchor->date->daysUntil(LocalDate::fromDateTime($fromWall)) * 86400
            + self::secondsIntoDay($fromWall) - $anchor->secondsFromMidnight;
        $high = intdiv(max(0, $elapsedWall) + 172800, $step) + 1;
        $low = 0;

        // Invariant: instant(low) <= from < instant(high)
        while ($low + 1 < $high) {
            $mid = intdiv($low + $high, 2);

            if ($this->sequenceInstant($anchor, $mid * $step) > $from) {
                $high = $mid;
            } else {
                $low = $mid;
            }
        }

        return $this->sequenceInstant($anchor, $high * $step) <= $to;
    }

    /**
     * The instant of the point $offsetSeconds past from by the wall
     * clock.
     */
    private function sequenceInstant(LocalDateTime $anchor, int $offsetSeconds): DateTimeImmutable
    {
        $total = $anchor->secondsFromMidnight + $offsetSeconds;

        return $anchor->date->addDays(intdiv($total, 86400))->atTime($total % 86400, $this->timezone);
    }

    private static function secondsIntoDay(DateTimeImmutable $wall): int
    {
        return (int) $wall->format('H') * 3600 + (int) $wall->format('i') * 60 + (int) $wall->format('s');
    }

    /**
     * The running month number since year zero (for scanning candidate
     * months).
     */
    private static function monthIndex(LocalDate $date): int
    {
        return $date->year * 12 + ($date->month - 1);
    }

    /**
     * @return array{int, int} [year, month]
     */
    private static function yearMonthAt(int $index): array
    {
        return [intdiv($index, 12), $index % 12 + 1];
    }
}
