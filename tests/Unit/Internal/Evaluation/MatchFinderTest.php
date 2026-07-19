<?php

namespace Yarunoka\Tests\Unit\Internal\Evaluation;

use Yarunoka\Definitions\BusinessDays;
use Yarunoka\Definitions\BusinessHolidays;
use Yarunoka\Definitions\CustomDefinition;
use Yarunoka\Definitions\Definitions;
use Yarunoka\Definitions\Holidays;
use Yarunoka\Internal\Evaluation\AtomDayEnumerator;
use Yarunoka\Internal\Evaluation\DayMatcher;
use Yarunoka\Internal\Evaluation\MatchFinder;
use Yarunoka\Internal\Evaluation\ResolvedDefinitions;
use Yarunoka\Internal\Evaluation\TimesExpander;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\YrnkSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Enumeration of candidate months and composition of if / shift / times
 * (the substance of matches / hasMatchIn). The date facts follow the
 * actual 2026 calendar (7/25 Sat, 7/31 Fri, 10/31 Sat).
 */
class MatchFinderTest extends TestCase
{
    // ---- matches: base day decision ----

    #[Test]
    public function parallel_axes_combine_with_and(): void
    {
        $finder = $this->finder();
        $schedule = $this->schedule(['years' => [2043], 'months' => [6], 'days' => [15], 'allday' => true]);

        // allday matches on the day alone and ignores the time.
        $this->assertTrue($finder->matches($schedule, $this->at('2043-06-15T12:34:56+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2044-06-15T12:34:56+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2043-07-15T12:34:56+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2043-06-16T12:34:56+09:00')));
    }

    #[Test]
    public function matches_checks_the_time_down_to_equality(): void
    {
        $finder = $this->finder();
        $schedule = $this->schedule(['days' => [25], 'times' => ['10:00']]);

        $this->assertTrue($finder->matches($schedule, $this->at('2026-07-25T10:00:00+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-07-25T10:00:01+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-07-25T09:59:59+09:00')));
    }

    #[Test]
    public function if_filters_without_moving_the_day(): void
    {
        $finder = $this->finder(holidays: ['2026-07-20']);
        $schedule = $this->schedule(['days' => ['mon'], 'if' => ['not', 'holiday'], 'times' => ['07:30']]);

        $this->assertFalse($finder->matches($schedule, $this->at('2026-07-20T07:30:00+09:00')));
        $this->assertTrue($finder->matches($schedule, $this->at('2026-07-13T07:30:00+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-07-21T07:30:00+09:00'))); // no moving
    }

    // ---- matches: shift ----

    #[Test]
    public function shift_rounds_the_base_day_to_the_landing_condition(): void
    {
        // 2026-07-25 is a Saturday → prev with or_same lands on 7/24
        // (Fri).
        $finder = $this->finder();
        $schedule = $this->schedule([
            'days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00'],
        ]);

        $this->assertTrue($finder->matches($schedule, $this->at('2026-07-24T10:00:00+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-07-25T10:00:00+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-07-23T10:00:00+09:00')));
        // 2026-09-25 is a Friday, so it stays on that day.
        $this->assertTrue($finder->matches($schedule, $this->at('2026-09-25T10:00:00+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-09-24T10:00:00+09:00')));
    }

    #[Test]
    public function shift_without_or_same_moves_strictly(): void
    {
        $finder = $this->finder();
        $schedule = $this->schedule(['days' => [25], 'shift' => ['prev', 'business_day'], 'times' => ['10:00']]);

        // 2026-09-25 is a Friday (a business day), yet excludes itself and
        // lands on 9/24 (Thu).
        $this->assertTrue($finder->matches($schedule, $this->at('2026-09-24T10:00:00+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-09-25T10:00:00+09:00')));
    }

    #[Test]
    public function consecutive_base_days_collapse_into_the_same_landing_day(): void
    {
        // 2026-07-18 (Sat), 19 (Sun), 20 (Mon, a holiday) → all 7/17
        // (Fri).
        $finder = $this->finder(holidays: ['2026-07-20']);
        $schedule = $this->schedule([
            'days' => ['business_holiday'], 'shift' => ['prev', 'business_day'], 'times' => ['10:00'],
        ]);

        $this->assertTrue($finder->matches($schedule, $this->at('2026-07-17T10:00:00+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-07-18T10:00:00+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-07-21T10:00:00+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-07-16T10:00:00+09:00')));
    }

    #[Test]
    public function a_shift_landing_can_cross_a_month_boundary(): void
    {
        $finder = $this->finder();
        // 2026-08-01 (Sat): prev of days [1] lands on 7/31 (Fri).
        $prev = $this->schedule(['days' => [1], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']]);
        // 2026-10-31 (Sat): next of last_day_of_month lands on 11/2 (Mon).
        $next = $this->schedule([
            'days' => ['last_day_of_month'], 'shift' => ['next', 'or_same', 'business_day'], 'times' => ['10:00'],
        ]);

        $this->assertTrue($finder->matches($prev, $this->at('2026-07-31T10:00:00+09:00')));
        $this->assertTrue($finder->matches($next, $this->at('2026-11-02T10:00:00+09:00')));
        $this->assertFalse($finder->matches($next, $this->at('2026-10-31T10:00:00+09:00')));
    }

    #[Test]
    public function if_filters_the_base_days_before_shift(): void
    {
        // 2026-07-25 is a Saturday. if removes the base day first, so
        // nothing lands on 7/24.
        $finder = $this->finder();
        $schedule = $this->schedule([
            'days' => [25], 'if' => ['not', 'sat'], 'shift' => ['prev', 'or_same', 'business_day'],
            'times' => ['10:00'],
        ]);

        $this->assertFalse($finder->matches($schedule, $this->at('2026-07-24T10:00:00+09:00')));
        $this->assertTrue($finder->matches($schedule, $this->at('2026-08-25T10:00:00+09:00')));
    }

    #[Test]
    public function cuts_off_and_does_not_land_when_no_day_satisfies_the_landing_condition(): void
    {
        // February has no 31st, so the base days are empty and nothing
        // lands anywhere.
        $finder = $this->finder();
        $schedule = $this->schedule([
            'days' => [31], 'months' => [2], 'shift' => ['prev', 'business_day'], 'times' => ['10:00'],
        ]);

        $this->assertFalse($finder->matches($schedule, $this->at('2026-02-27T10:00:00+09:00')));
        $this->assertFalse($finder->matches($schedule, $this->at('2026-02-28T10:00:00+09:00')));
    }

    // ---- hasMatchIn: interval questions ----

    #[Test]
    public function the_half_open_interval_excludes_the_from_point_and_includes_the_to_point(): void
    {
        $finder = $this->finder();
        $schedule = $this->schedule(['times' => ['09:00']]);

        $this->assertFalse($finder->hasMatchIn(
            $schedule, $this->at('2026-07-12T09:00:00+09:00'), $this->at('2026-07-12T09:30:00+09:00'),
        ));
        $this->assertTrue($finder->hasMatchIn(
            $schedule, $this->at('2026-07-12T08:30:00+09:00'), $this->at('2026-07-12T09:00:00+09:00'),
        ));
        $this->assertFalse($finder->hasMatchIn(
            $schedule, $this->at('2026-07-12T09:00:00+09:00'), $this->at('2026-07-12T09:00:00+09:00'),
        ));
    }

    #[Test]
    public function searches_across_candidate_months(): void
    {
        $finder = $this->finder();
        $schedule = $this->schedule(['months' => [6], 'days' => [15], 'times' => ['10:00']]);

        // Even for an interval from the start of the year, June is the
        // only candidate.
        $this->assertTrue($finder->hasMatchIn(
            $schedule, $this->at('2026-01-01T00:00:00+09:00'), $this->at('2026-12-31T00:00:00+09:00'),
        ));
        $this->assertFalse($finder->hasMatchIn(
            $schedule, $this->at('2026-06-15T10:00:00+09:00'), $this->at('2026-12-31T00:00:00+09:00'),
        ));
    }

    #[Test]
    public function a_base_day_of_an_earlier_month_spills_into_the_interval_by_a_forward_shift(): void
    {
        // The 2026-10-31 (Sat) base day lands on 11/2 (Mon) via next.
        // Even a question about November alone must look at October's
        // base days or it misses this.
        $finder = $this->finder();
        $schedule = $this->schedule([
            'days' => ['last_day_of_month'], 'shift' => ['next', 'or_same', 'business_day'], 'times' => ['10:00'],
        ]);

        $this->assertTrue($finder->hasMatchIn(
            $schedule, $this->at('2026-11-01T00:00:00+09:00'), $this->at('2026-11-02T23:59:00+09:00'),
        ));
        // The 11/2 10:00 point is exactly from and does not count. The
        // November base day 11/30 (Mon) lands on 11/30, so there is no
        // point up to 11/29.
        $this->assertFalse($finder->hasMatchIn(
            $schedule, $this->at('2026-11-02T10:00:00+09:00'), $this->at('2026-11-29T23:59:00+09:00'),
        ));
    }

    #[Test]
    public function a_base_day_of_a_later_month_spills_into_the_interval_by_a_backward_shift(): void
    {
        // The 2026-08-01 (Sat) base day lands on 7/31 (Fri) via prev.
        // Even a question about July alone must look at August's base
        // days or it misses this.
        $finder = $this->finder();
        $schedule = $this->schedule(['days' => [1], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']]);

        $this->assertTrue($finder->hasMatchIn(
            $schedule, $this->at('2026-07-30T00:00:00+09:00'), $this->at('2026-07-31T23:59:00+09:00'),
        ));
        $this->assertFalse($finder->hasMatchIn(
            $schedule, $this->at('2026-07-02T00:00:00+09:00'), $this->at('2026-07-30T23:59:00+09:00'),
        ));
    }

    #[Test]
    public function spilling_can_cross_a_year_boundary(): void
    {
        $finder = $this->finder();
        // The 2028-12-31 (Sun) base day lands on 2029-01-01 (Mon) via
        // next. Even an interval covering only January of the next year
        // must look at December of the previous year.
        $next = $this->schedule([
            'days' => ['last_day_of_month'], 'shift' => ['next', 'or_same', 'business_day'], 'times' => ['10:00'],
        ]);
        // The 2028-01-01 (Sat) base day lands on 2027-12-31 (Fri) via
        // prev.
        $prev = $this->schedule(['days' => [1], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']]);

        $this->assertTrue($finder->hasMatchIn(
            $next, $this->at('2029-01-01T00:00:00+09:00'), $this->at('2029-01-01T23:59:00+09:00'),
        ));
        // A point exactly at from does not count, and there is no point
        // before January's next landing (1/31).
        $this->assertFalse($finder->hasMatchIn(
            $next, $this->at('2029-01-01T10:00:00+09:00'), $this->at('2029-01-02T00:00:00+09:00'),
        ));
        $this->assertTrue($finder->hasMatchIn(
            $prev, $this->at('2027-12-30T00:00:00+09:00'), $this->at('2027-12-31T23:59:00+09:00'),
        ));
    }

    #[Test]
    public function a_shift_landing_searches_up_to_exactly_the_366_day_contract(): void
    {
        // A sparse custom landing condition creates the distance.
        // 2027-06-16 is exactly 366 days from the 2026-06-15 base day.
        $lands = $this->finder(custom: ['desk-open-day' => ['2027-06-16']]);
        $schedule = $this->schedule([
            'years' => [2026], 'months' => [6], 'days' => [15],
            'shift' => ['next', 'or_same', 'desk-open-day'], 'times' => ['10:00'],
        ]);

        $this->assertTrue($lands->matches($schedule, $this->at('2027-06-16T10:00:00+09:00')));
        // The 11 months in between have empty base days; the walk still
        // picks up the 2026-06 base day.
        $this->assertTrue($lands->hasMatchIn(
            $schedule, $this->at('2027-06-16T00:00:00+09:00'), $this->at('2027-06-16T23:59:00+09:00'),
        ));
    }

    #[Test]
    public function a_shift_landing_beyond_the_366_day_contract_does_not_land(): void
    {
        // 2027-06-17 is 367 days from 2026-06-15. A contract violation, so
        // it does not land.
        $tooFar = $this->finder(custom: ['desk-open-day' => ['2027-06-17']]);
        $schedule = $this->schedule([
            'years' => [2026], 'months' => [6], 'days' => [15],
            'shift' => ['next', 'or_same', 'desk-open-day'], 'times' => ['10:00'],
        ]);

        $this->assertFalse($tooFar->matches($schedule, $this->at('2027-06-17T10:00:00+09:00')));
        $this->assertFalse($tooFar->hasMatchIn(
            $schedule, $this->at('2027-06-17T00:00:00+09:00'), $this->at('2027-06-17T23:59:00+09:00'),
        ));
    }

    #[Test]
    public function finishes_by_the_contract_distance_even_across_months_without_base_days(): void
    {
        // A schedule whose base days exist only in 2026-06 (via years /
        // months), asked about an interval too far for any landing to
        // reach. The months in between have empty base days and give no
        // monotonicity signal, so the 366-day contract alone cuts off the
        // walk.
        $finder = $this->finder();
        $next = $this->schedule([
            'years' => [2026], 'months' => [6], 'days' => [15],
            'shift' => ['next', 'or_same', 'business_day'], 'times' => ['10:00'],
        ]);
        $prev = $this->schedule([
            'years' => [2026], 'months' => [6], 'days' => [15],
            'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00'],
        ]);

        // 2026-06-15 (Mon) is a business day and lands on itself. The
        // intervals are more than 366 days after / before it.
        $this->assertTrue($finder->matches($next, $this->at('2026-06-15T10:00:00+09:00')));
        $this->assertFalse($finder->hasMatchIn(
            $next, $this->at('2027-09-01T00:00:00+09:00'), $this->at('2027-09-30T23:59:00+09:00'),
        ));
        $this->assertFalse($finder->hasMatchIn(
            $prev, $this->at('2025-03-01T00:00:00+09:00'), $this->at('2025-04-30T23:59:00+09:00'),
        ));
    }

    #[Test]
    public function a_month_wiped_out_by_if_is_skipped_and_the_next_month_matches(): void
    {
        // 2026-07-25 is a Saturday and if removes it. The next base day is
        // 8/25 (Tue).
        $finder = $this->finder();
        $schedule = $this->schedule(['days' => [25], 'if' => ['not', 'sat'], 'times' => ['10:00']]);

        $this->assertFalse($finder->hasMatchIn(
            $schedule, $this->at('2026-07-01T00:00:00+09:00'), $this->at('2026-07-31T23:59:00+09:00'),
        ));
        $this->assertTrue($finder->hasMatchIn(
            $schedule, $this->at('2026-07-01T00:00:00+09:00'), $this->at('2026-08-25T10:00:00+09:00'),
        ));
    }

    #[Test]
    public function a_schedule_that_never_matches_becomes_no_when_the_candidates_run_out(): void
    {
        // No horizon scanning: once years rules out every candidate month
        // of the interval, the search is over.
        $finder = $this->finder();
        $schedule = $this->schedule(['years' => [2020], 'months' => [6], 'days' => [15], 'allday' => true]);

        $this->assertFalse($finder->hasMatchIn(
            $schedule, $this->at('2026-01-01T00:00:00+09:00'), $this->at('2026-12-31T23:59:00+09:00'),
        ));
    }

    #[Test]
    public function the_allday_point_stands_at_the_start_of_the_day(): void
    {
        $finder = $this->finder();
        $schedule = $this->schedule(['days' => [['3rd', 'mon']], 'allday' => true]);

        $this->assertTrue($finder->hasMatchIn(
            $schedule, $this->at('2026-07-19T23:59:00+09:00'), $this->at('2026-07-20T00:00:00+09:00'),
        ));
        $this->assertFalse($finder->hasMatchIn(
            $schedule, $this->at('2026-07-20T00:00:00+09:00'), $this->at('2026-07-20T23:59:00+09:00'),
        ));
    }

    #[Test]
    public function any_of_several_time_points_inside_the_interval_matches(): void
    {
        $finder = $this->finder();
        $schedule = $this->schedule(['days' => ['mon'], 'times' => ['08:00', '12:00']]);

        $this->assertTrue($finder->hasMatchIn(
            $schedule, $this->at('2026-07-13T09:00:00+09:00'), $this->at('2026-07-13T12:00:00+09:00'),
        ));
        $this->assertFalse($finder->hasMatchIn(
            $schedule, $this->at('2026-07-13T12:01:00+09:00'), $this->at('2026-07-13T23:59:00+09:00'),
        ));
    }

    // ---- helpers ----

    /**
     * @param  list<string>  $holidays
     * @param  array<string, list<string>>  $custom
     */
    private function finder(array $holidays = [], array $custom = []): MatchFinder
    {
        $resolved = new ResolvedDefinitions(new Definitions(
            holidays: Holidays::ofDates($holidays),
            businessHolidays: BusinessHolidays::ofDates([]),
            businessDays: BusinessDays::ofDates([]),
            custom: array_map(
                static fn (array $dates): CustomDefinition => CustomDefinition::ofDates($dates),
                $custom,
            ),
        ), resolvers: []);
        $dayMatcher = new DayMatcher($resolved);

        return new MatchFinder(
            $dayMatcher,
            new AtomDayEnumerator($dayMatcher),
            new TimesExpander($resolved),
            new DateTimeZone('Asia/Tokyo'),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function schedule(array $raw): YrnkSchedule
    {
        return (new ScheduleParser)->parse($raw);
    }

    private function at(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso);
    }
}
