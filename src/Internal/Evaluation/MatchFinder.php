<?php

namespace Yarunoka\Internal\Evaluation;

use Yarunoka\Schedule\AllDay;
use Yarunoka\Schedule\DayCycle;
use Yarunoka\Schedule\EverySequence;
use Yarunoka\Schedule\IfGuard;
use Yarunoka\Schedule\Shift;
use Yarunoka\Internal\Vocabulary\Direction;
use Yarunoka\YrnkDate;
use Yarunoka\YrnkDateTime;
use Yarunoka\YrnkSchedule;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Enumeration of candidate months and composition of if / shift / times
 * (the substance of matches / hasMatchIn / occurrencesIn).
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

    /**
     * Margin around a question range covering how far a wall reading
     * can sit from its instant: two days, comfortably above the widest
     * offset a zone can apply (UTC−12 to UTC+14).
     */
    private const int WALL_OFFSET_SLACK_SECONDS = 172800;

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
     * the from / until clipping applies to its comparison instant (00:00
     * of its day, resolved like any other wall-clock point).
     */
    public function matches(YrnkSchedule $schedule, DateTimeImmutable $at): bool
    {
        if ($schedule->times instanceof EverySequence) {
            // Points are whole seconds, so "is there a point in
            // (at−1 second, at]" = "is at a point".
            return $this->hasMatchIn($schedule, self::secondBefore($at), $at);
        }

        $day = $this->wallDateOf($at);

        if (! $this->dayMatches($schedule, $day) && ! $this->vanishedDayLandsOn($schedule, $day)) {
            return false;
        }

        if ($schedule->times instanceof AllDay) {
            return $this->withinRange($schedule, $this->atTime($day, 0));
        }

        // Compare against the day's points resolved to instants, not
        // against wall-clock seconds. On a DST transition day the wall
        // clock and the points diverge (a nonexistent time is pushed
        // forward, a time that occurs twice counts only as its first
        // occurrence — RFC 5545 §3.3.5), so point generation is aligned
        // with hasMatchIn via the same atTime.
        $timestamp = $at->getTimestamp();

        foreach ($this->expander->secondsOf($schedule->times) as $second) {
            $instant = $this->atTime($day, $second);

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
            $lower = self::secondBefore($schedule->from);

            if ($lower > $from) {
                $from = $lower;
            }
        }

        if ($schedule->until !== null) {
            $upper = self::secondBefore($schedule->until);

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

        $fromDay = $this->wallDateOf($from);
        $toDay = $this->wallDateOf($to);

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
     * The occurrences in the closed interval [from, to] (both boundary
     * instants included), in ascending order of comparison instant.
     * Timed occurrences are answered as instants, all-day occurrences as
     * dates (YrnkDate).
     *
     * @return list<YrnkDate|YrnkDateTime>
     */
    public function occurrencesIn(YrnkSchedule $schedule, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        // Fold the validity range [valid-from, until) into the closed
        // window [from, to]. Points are whole seconds, so t < until ⇔
        // t <= until − 1s; valid-from needs no adjustment (both bounds
        // are inclusive).
        if ($schedule->from !== null) {
            $lower = $schedule->from;

            if ($lower > $from) {
                $from = $lower;
            }
        }

        if ($schedule->until !== null) {
            $upper = self::secondBefore($schedule->until);

            if ($upper < $to) {
                $to = $upper;
            }
        }

        if ($from > $to) {
            return [];
        }

        if ($schedule->times instanceof EverySequence) {
            return $this->sequenceOccurrencesIn($schedule->from, $schedule->times, $from, $to);
        }

        $fromDay = $this->wallDateOf($from);
        $toDay = $this->wallDateOf($to);
        $days = $this->landedDaysWithin($schedule, $fromDay, $toDay);

        if ($schedule->times instanceof AllDay) {
            $dates = [];

            foreach ($days as $day) {
                $instant = $this->atTime($day, 0);

                if ($instant >= $from && $instant <= $to) {
                    $dates[] = $day;
                }
            }

            return $dates;
        }

        // Points are keyed by timestamp: distinct wall times folded onto
        // one instant by a DST gap (RFC 5545 §3.3.5) collapse, and the
        // final ksort orders by instant even where the fold locally
        // reverses the wall-clock order.
        $seconds = $this->expander->secondsOf($schedule->times);
        $instants = [];

        foreach ($days as $day) {
            foreach ($seconds as $second) {
                $instant = $this->atTime($day, $second);

                if ($instant >= $from && $instant <= $to) {
                    $instants[$instant->getTimestamp()] = $instant;
                }
            }
        }

        ksort($instants);

        return array_values($instants);
    }

    /**
     * The landing days inside [fromDay, toDay], ascending and without
     * duplicates. Months overlapping the window carry the base days
     * whose landings can lie inside it; with a shift, base days of
     * months on the opposite side of the shift direction can spill in,
     * so those months are walked with the same cutoffs as the spilled
     * interval checks below.
     *
     * @return list<YrnkDate>
     */
    private function landedDaysWithin(YrnkSchedule $schedule, YrnkDate $fromDay, YrnkDate $toDay): array
    {
        $found = [];

        for ($index = self::monthIndex($fromDay); $index <= self::monthIndex($toDay); $index++) {
            [$year, $month] = self::yearMonthAt($index);
            $this->collectWithin($this->landedDaysIn($schedule, $year, $month), $fromDay, $toDay, $found);
        }

        if ($schedule->shift?->direction === Direction::Next) {
            for ($index = self::monthIndex($fromDay) - 1; ; $index--) {
                [$year, $month] = self::yearMonthAt($index);
                $monthLast = $this->lastDayOf($year, $month);

                if ($fromDay > $this->addDays($monthLast, self::SHIFT_SEARCH_LIMIT_DAYS)) {
                    break;
                }

                $landed = $this->landedDaysIn($schedule, $year, $month);
                $this->collectWithin($landed, $fromDay, $toDay, $found);

                if ($landed !== [] && $fromDay > $landed[array_key_last($landed)]) {
                    break;
                }
            }
        }

        if ($schedule->shift?->direction === Direction::Prev) {
            for ($index = self::monthIndex($toDay) + 1; ; $index++) {
                [$year, $month] = self::yearMonthAt($index);

                if ($this->addDays($this->dayAt($year, $month, 1), -self::SHIFT_SEARCH_LIMIT_DAYS) > $toDay) {
                    break;
                }

                $landed = $this->landedDaysIn($schedule, $year, $month);
                $this->collectWithin($landed, $fromDay, $toDay, $found);

                if ($landed !== [] && $landed[0] > $toDay) {
                    break;
                }
            }
        }

        ksort($found, SORT_STRING);

        return array_values($found);
    }

    /**
     * @param  list<YrnkDate>  $days
     * @param  array<string, YrnkDate>  $found  Keyed by the ISO date, so cross-month duplicates collapse
     */
    private function collectWithin(array $days, YrnkDate $fromDay, YrnkDate $toDay, array &$found): void
    {
        foreach ($days as $day) {
            if ($fromDay <= $day && $day <= $toDay) {
                $found[$day->format('Y-m-d')] = $day;
            }
        }
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
        YrnkDate $fromDay,
        YrnkDate $toDay,
    ): bool {
        for ($index = self::monthIndex($fromDay) - 1; ; $index--) {
            [$year, $month] = self::yearMonthAt($index);
            $monthLast = $this->lastDayOf($year, $month);

            if ($fromDay > $this->addDays($monthLast, self::SHIFT_SEARCH_LIMIT_DAYS)) {
                return false;
            }

            $landed = $this->landedDaysIn($schedule, $year, $month);

            if ($this->hasInstantIn($landed, $seconds, $from, $to, $fromDay, $toDay)) {
                return true;
            }

            if ($landed !== [] && $fromDay > $landed[array_key_last($landed)]) {
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
        YrnkDate $fromDay,
        YrnkDate $toDay,
    ): bool {
        for ($index = self::monthIndex($toDay) + 1; ; $index++) {
            [$year, $month] = self::yearMonthAt($index);
            $monthFirst = $this->dayAt($year, $month, 1);

            if ($this->addDays($monthFirst, -self::SHIFT_SEARCH_LIMIT_DAYS) > $toDay) {
                return false;
            }

            $landed = $this->landedDaysIn($schedule, $year, $month);

            if ($this->hasInstantIn($landed, $seconds, $from, $to, $fromDay, $toDay)) {
                return true;
            }

            if ($landed !== [] && $landed[0] > $toDay) {
                return false;
            }
        }
    }

    /**
     * The base days of the month (the AND of years / months / days,
     * filtered by if) mapped to their shift landing days, in ascending
     * order. Consecutive base days collapse into the same landing day.
     *
     * @return list<YrnkDate>
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
            ? range(1, (int) $this->dayAt($year, $month, 1)->format('t'))
            : $this->enumerateAtomDays($schedule, $year, $month);

        $landed = [];

        foreach ($dayNumbers as $dayNumber) {
            $base = $this->dayAt($year, $month, $dayNumber);

            if (! $this->passesIf($schedule->if, $base)) {
                continue;
            }

            $day = $schedule->shift === null ? $base : $this->landingOf($schedule->shift, $base);

            if ($day === null) {
                continue;
            }

            if ($landed !== [] && self::isSameDay($landed[array_key_last($landed)], $day)) {
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
        /** @var YrnkDateTime $anchor The YrnkSchedule invariant requires from for vocabulary that counts */
        $anchor = $schedule->from;
        $first = $this->dayAt($year, $month, 1);
        $offset = self::daysBetween($anchor, $first);

        if ($offset <= 0) {
            // The from day is on or after the first of the month (the
            // count starts in this month or later).
            $startDay = 1 - $offset;
        } else {
            $remainder = $offset % $atom->intervalDays;
            $startDay = 1 + ($remainder === 0 ? 0 : $atom->intervalDays - $remainder);
        }

        $days = [];

        for ($day = $startDay; $day <= (int) $first->format('t'); $day += $atom->intervalDays) {
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
     * @param  list<YrnkDate>  $days
     * @param  list<int>  $seconds
     */
    private function hasInstantIn(
        array $days,
        array $seconds,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        YrnkDate $fromDay,
        YrnkDate $toDay,
    ): bool {
        foreach ($days as $day) {
            if ($fromDay > $day || $day > $toDay) {
                continue;
            }

            if (! self::isSameDay($day, $fromDay) && ! self::isSameDay($day, $toDay)) {
                return true;
            }

            foreach ($seconds as $second) {
                $instant = $this->atTime($day, $second);

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
    private function dayMatches(YrnkSchedule $schedule, YrnkDate $date): bool
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

            $cursor = $this->addDays($cursor, $step);

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

    /**
     * Where a zone skips a calendar day outright, the occurrence chosen
     * for that day stands on the day it resolves to. The day check reads
     * a day off the instant and asks whether that day matches, so it
     * never sees the day that vanished — the enumeration, which chooses
     * days on the calendar before resolving them, is asked instead. The
     * question is only put when a day really did vanish into this one,
     * which the whole tz database does five times.
     */
    private function vanishedDayLandsOn(YrnkSchedule $schedule, YrnkDate $day): bool
    {
        if (! self::isSameDay($this->addDays($day, -1), $day)) {
            return false;
        }

        return $this->landedDaysWithin($schedule, $day, $day) !== [];
    }

    private function isBaseDay(YrnkSchedule $schedule, YrnkDate $date): bool
    {
        if ($schedule->years !== null && ! in_array((int) $date->format('Y'), $schedule->years, true)) {
            return false;
        }

        if ($schedule->months !== null && ! in_array((int) $date->format('n'), $schedule->months, true)) {
            return false;
        }

        if ($schedule->days !== null && ! $this->matchesAnyAtom($schedule, $date)) {
            return false;
        }

        return $this->passesIf($schedule->if, $date);
    }

    private function matchesAnyAtom(YrnkSchedule $schedule, YrnkDate $date): bool
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
    private function matchesCycle(YrnkSchedule $schedule, DayCycle $atom, YrnkDate $date): bool
    {
        /** @var YrnkDateTime $anchor The YrnkSchedule invariant requires from for vocabulary that counts */
        $anchor = $schedule->from;
        $offset = self::daysBetween($anchor, $date);

        return $offset >= 0 && $offset % $atom->intervalDays === 0;
    }

    /**
     * if filters without moving the day. shift then moves the filtered
     * result as base days.
     */
    private function passesIf(?IfGuard $if, YrnkDate $date): bool
    {
        if ($if === null) {
            return true;
        }

        $target = $if->direction === null ? $date : $this->addDays($date, $if->direction->step());
        $result = $this->dayMatcher->matches($if->condition, $target);

        return $if->negated ? ! $result : $result;
    }

    private function landsOn(Shift $shift, YrnkDate $base, YrnkDate $target): bool
    {
        $landing = $this->landingOf($shift, $base);

        return $landing !== null && self::isSameDay($landing, $target);
    }

    /**
     * The landing day of a base day (the forward landing computation).
     * Walks in the given direction until the landing condition holds.
     * or_same includes the base day itself; the strict form advances one
     * day before searching. The maximum displacement from the base day is
     * the same 366 days for both forms, so the strict walk tests one
     * candidate fewer.
     */
    private function landingOf(Shift $shift, YrnkDate $base): ?YrnkDate
    {
        $cursor = $shift->orSame ? $base : $this->addDays($base, $shift->direction->step());

        for ($displacement = $shift->orSame ? 0 : 1; $displacement <= self::SHIFT_SEARCH_LIMIT_DAYS; $displacement++) {
            if ($this->dayMatcher->matches($shift->condition, $cursor)) {
                return $cursor;
            }

            $cursor = $this->addDays($cursor, $shift->direction->step());
        }

        return null;
    }

    /**
     * Is the point inside the validity range [from, until)?
     */
    private function withinRange(YrnkSchedule $schedule, DateTimeImmutable $instant): bool
    {
        if ($schedule->from !== null && $instant < $schedule->from) {
            return false;
        }

        if ($schedule->until !== null && $instant >= $schedule->until) {
            return false;
        }

        return true;
    }

    /**
     * Is there a point of the interval sequence (from + k × interval) in
     * (from, to]? Answered exactly, per offset segment: the row is laid
     * out on the wall clock, whose offset to real time is
     * piecewise-constant, so within one segment wall order and instant
     * order agree and the question reduces to intersecting integer
     * ranges. No instant-order assumption is made over the whole row —
     * a wall time pushed out of a DST gap stands after later wall
     * times' instants.
     *
     * @param  ?YrnkDateTime  $anchor  Never null: the YrnkSchedule invariant requires from for vocabulary that counts
     */
    private function sequenceHasMatchIn(
        ?YrnkDateTime $anchor,
        EverySequence $sequence,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): bool {
        if ($anchor === null) {
            return false;
        }

        // Points are whole seconds: (from, to] on instants is the
        // integer range [from's whole second + 1, to's whole second].
        return $this->sequencePointRunsIn(
            $anchor,
            $sequence->stepSeconds(),
            $from->getTimestamp() + 1,
            $to->getTimestamp(),
        ) !== [];
    }

    /**
     * The row's points whose instants lie in [lower, upper] (epoch
     * seconds), as [firstWall, lastWall, offset] runs on the wall-epoch
     * scale: the row points firstWall, firstWall + step, …, lastWall,
     * each resolving to the instant wall − offset. Runs of different
     * segments can interleave in instant terms (a pushed run stands past
     * a later segment's first instants).
     *
     * @return list<array{int, int, int}>
     */
    private function sequencePointRunsIn(YrnkDateTime $anchor, int $step, int $lower, int $upper): array
    {
        if ($lower > $upper) {
            return [];
        }

        $anchorWall = self::wallEpochOf($anchor);
        $runs = [];

        foreach ($this->wallOffsetSegments($lower, $upper) as [$segmentStart, $segmentEnd, $offset]) {
            $first = max($segmentStart, $lower + $offset, $anchorWall);
            $last = min($segmentEnd - 1, $upper + $offset);

            if ($first > $last) {
                continue;
            }

            // Snap both ends onto the row (the first point at or after
            // first, the last at or before last).
            $first = $anchorWall + intdiv($first - $anchorWall + $step - 1, $step) * $step;
            $last = $anchorWall + intdiv($last - $anchorWall, $step) * $step;

            if ($first > $last) {
                continue;
            }

            $runs[] = [$first, $last, $offset];
        }

        return $runs;
    }

    /**
     * The wall clock's offset regimes as [wallStart, wallEnd, offset]
     * segments (wall-epoch bounds, end exclusive) covering every wall
     * time that can resolve into the instant range [lower, upper]. The
     * offset applied to a wall time changes at the boundary wall
     * b = transition instant + max(offset before, offset after): below b
     * the earlier offset applies — which both pushes gap wall times
     * forward and reads an overlap wall time as its first occurrence
     * (RFC 5545 §3.3.5).
     *
     * @return non-empty-list<array{int, int, int}>
     */
    private function wallOffsetSegments(int $lower, int $upper): array
    {
        // getTransitions reports false (not a list) for offset- and
        // abbreviation-type zones; fold that into the empty list.
        $transitions = $this->timezone->getTransitions(
            $lower - self::WALL_OFFSET_SLACK_SECONDS,
            $upper + self::WALL_OFFSET_SLACK_SECONDS,
        ) ?: [];

        if ($transitions === []) {
            // Offset- and abbreviation-type zones carry no transition
            // list; they are a single regime.
            return [[PHP_INT_MIN, PHP_INT_MAX, $this->timezone->getOffset(new DateTimeImmutable("@{$lower}"))]];
        }

        $segments = [];
        $start = PHP_INT_MIN;
        $offset = $transitions[0]['offset'];

        foreach (array_slice($transitions, 1) as $transition) {
            $boundary = $transition['ts'] + max($offset, $transition['offset']);
            $segments[] = [$start, $boundary, $offset];
            $start = $boundary;
            $offset = $transition['offset'];
        }

        $segments[] = [$start, PHP_INT_MAX, $offset];

        return $segments;
    }

    /**
     * The wall reading as seconds on a fake-UTC epoch scale (the wall
     * calendar laid out with no offsets) — the scale the sequence row
     * and the offset segments are intersected on.
     */
    private static function wallEpochOf(YrnkDateTime $wall): int
    {
        return self::utcMidnightOf($wall)->getTimestamp()
            + (int) $wall->format('H') * 3600 + (int) $wall->format('i') * 60 + (int) $wall->format('s');
    }

    /**
     * The instant one second earlier. The subtraction runs on UTC:
     * modify() works on the wall clock of the value's own timezone, and
     * one second before the end of a DST gap reads as a nonexistent
     * wall time that resolves a whole gap away.
     */
    private static function secondBefore(DateTimeImmutable $instant): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->modify('-1 second');
    }

    /**
     * The points of the interval sequence (from + k × interval) inside
     * the closed window [from, to], ascending by instant: the
     * per-segment runs collected keyed by timestamp — deduplicating
     * points folded together by a DST gap and ordering interleaved runs
     * by instant at once.
     *
     * @param  ?YrnkDateTime  $anchor  Never null: the YrnkSchedule invariant requires from for vocabulary that counts
     * @return list<YrnkDateTime>
     */
    private function sequenceOccurrencesIn(
        ?YrnkDateTime $anchor,
        EverySequence $sequence,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        if ($anchor === null) {
            return [];
        }

        // Points are whole seconds: the closed [from, to] on instants is
        // the integer range [from's second rounded up, to's second
        // rounded down].
        $lower = $from->getTimestamp() + ((int) $from->format('u') > 0 ? 1 : 0);
        $step = $sequence->stepSeconds();
        $instants = [];

        foreach ($this->sequencePointRunsIn($anchor, $step, $lower, $to->getTimestamp()) as [$first, $last, $offset]) {
            for ($wall = $first; $wall <= $last; $wall += $step) {
                $timestamp = $wall - $offset;
                // Wrapped rather than re-read from the wall clock: an
                // overlap wall time names two instants, and this is the
                // one the run resolved to.
                $instants[$timestamp] = YrnkDateTime::createFromInterface(
                    (new DateTimeImmutable("@{$timestamp}"))->setTimezone($this->timezone),
                );
            }
        }

        ksort($instants);

        return array_values($instants);
    }

    /**
     * The running month number since year zero (for scanning candidate
     * months).
     */
    private static function monthIndex(YrnkDate $date): int
    {
        return (int) $date->format('Y') * 12 + ((int) $date->format('n') - 1);
    }

    /**
     * @return array{int, int} [year, month]
     */
    private static function yearMonthAt(int $index): array
    {
        return [intdiv($index, 12), $index % 12 + 1];
    }

    /**
     * The wall date the instant reads as on the document's clock.
     */
    private function wallDateOf(DateTimeImmutable $instant): YrnkDate
    {
        return new YrnkDate($instant->setTimezone($this->timezone)->format('Y-m-d'), $this->timezone);
    }

    private function dayAt(int $year, int $month, int $day): YrnkDate
    {
        return new YrnkDate(sprintf('%04d-%02d-%02d', $year, $month, $day), $this->timezone);
    }

    private function lastDayOf(int $year, int $month): YrnkDate
    {
        $first = $this->dayAt($year, $month, 1);

        return $this->dayAt($year, $month, (int) $first->format('t'));
    }

    /**
     * The day $days calendar days away. The step runs on UTC so that a
     * transition never shortens or lengthens a step, and the result is
     * read back on the document's clock.
     */
    private function addDays(YrnkDate $date, int $days): YrnkDate
    {
        $shifted = self::utcMidnightOf($date)->modify(sprintf('%+d days', $days));

        return new YrnkDate($shifted->format('Y-m-d'), $this->timezone);
    }

    /**
     * The point $secondsFromMidnight past the start of the day, resolved
     * on the document's clock like any other wall-clock point. Built from
     * the wall reading rather than by setTime on the day, whose late
     * static binding would hand back a YrnkDate — an all-day value where
     * a timed one belongs.
     */
    private function atTime(YrnkDate $day, int $secondsFromMidnight): YrnkDateTime
    {
        return new YrnkDateTime(
            sprintf(
                '%s %02d:%02d:%02d',
                $day->format('Y-m-d'),
                intdiv($secondsFromMidnight, 3600),
                intdiv($secondsFromMidnight % 3600, 60),
                $secondsFromMidnight % 60,
            ),
            $this->timezone,
        );
    }

    private static function isSameDay(YrnkDate $a, YrnkDate $b): bool
    {
        return $a->format('Y-m-d') === $b->format('Y-m-d');
    }

    /**
     * The number of calendar days from $from's day to $to's day. Counted
     * on UTC so that neither end's offset nor a transition between them
     * enters the difference.
     */
    private static function daysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return intdiv(
            self::utcMidnightOf($to)->getTimestamp() - self::utcMidnightOf($from)->getTimestamp(),
            86400,
        );
    }

    /**
     * The value's wall date placed on UTC — the tz-free scale the day
     * arithmetic runs on.
     */
    private static function utcMidnightOf(DateTimeImmutable $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value->format('Y-m-d'), new DateTimeZone('UTC'));
    }
}
