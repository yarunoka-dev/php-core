<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Calendar\BusinessDays;
use Yarunoka\Calendar\BusinessHolidays;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Calendar\CustomDefinition;
use Yarunoka\Calendar\Holidays;
use Yarunoka\Calendar\Workweek;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies, through matches (the single check), the semantics of day
 * expressions, the layer model, shift, if, and how times take part. The
 * date facts follow the actual 2026 calendar (7/11 Sat, 7/12 Sun, 7/13
 * Mon, …).
 */
class MatchesTest extends TestCase
{
    // ---- how times take part ----

    #[Test]
    public function with_times_the_value_must_equal_one_of_the_expanded_points_to_the_second(): void
    {
        $evaluator = $this->evaluator();
        $schedule = $this->schedule(['days' => [25], 'times' => ['10:00', '15:30']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-25T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-25T15:30:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-25T10:00:01+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-25T09:59:59+09:00')));
    }

    #[Test]
    public function allday_checks_the_day_alone_and_ignores_the_time(): void
    {
        $evaluator = $this->evaluator();
        $schedule = $this->schedule(['days' => [25], 'allday' => true]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-25T00:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-25T18:42:07+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-24T18:42:07+09:00')));
    }

    #[Test]
    public function allday_is_not_a_shorthand_for_a_timed_occurrence_at_midnight(): void
    {
        // The spec keeps the two kinds of occurrence distinct: a timed
        // 00:00 point is an instant and matches that instant alone,
        // while an all-day occurrence is day-level and time does not
        // apply to it. The 00:00 placement of an all-day occurrence is
        // its comparison instant for range questions, not a time.
        $evaluator = $this->evaluator();
        $timed = $this->schedule(['days' => [25], 'times' => ['00:00']]);
        $allday = $this->schedule(['days' => [25], 'allday' => true]);

        $this->assertTrue($evaluator->matches($timed, $this->at('2026-07-25T00:00:00+09:00')));
        $this->assertFalse($evaluator->matches($timed, $this->at('2026-07-25T15:00:00+09:00')));
        $this->assertTrue($evaluator->matches($allday, $this->at('2026-07-25T00:00:00+09:00')));
        $this->assertTrue($evaluator->matches($allday, $this->at('2026-07-25T15:00:00+09:00')));
    }

    #[Test]
    public function allday_ignores_the_time_on_a_shift_landing_day_too(): void
    {
        // 2026-07-25 is a Saturday → lands on 7/24 (Fri). 2026-09-25 is a
        // Friday → stays.
        $evaluator = $this->evaluator();
        $schedule = $this->schedule([
            'days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'allday' => true,
        ]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-24T15:23:45+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-25T15:23:45+09:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-09-25T09:00:00+09:00')));
    }

    #[Test]
    public function sub_second_precision_is_truncated_for_comparison(): void
    {
        // The DSL's scheduled points are never finer than a second.
        // Granularity adjustments are the caller's job.
        $evaluator = $this->evaluator();
        $schedule = $this->schedule(['days' => [25], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-25T10:00:00.500000+09:00')));
    }

    #[Test]
    public function matches_the_points_of_a_grid_too(): void
    {
        $evaluator = $this->evaluator();
        $schedule = $this->schedule([
            'days' => ['mon'], 'times' => ['every' => [1, 'hour'], 'between' => ['08:00', '20:00']],
        ]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-13T08:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-13T19:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-13T20:00:00+09:00'))); // outside the half-open interval
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-13T08:30:00+09:00')));
    }

    // ---- DST transitions (RFC 5545 §3.3.5) ----

    #[Test]
    public function a_point_at_a_nonexistent_wall_time_matches_the_instant_pushed_forward(): void
    {
        // America/New_York transitions 02:00 EST → 03:00 EDT on
        // 2026-03-08. The wall time 02:30 does not exist; the point
        // stands at the pre-transition-offset interpretation = the
        // instant 03:30 EDT.
        $evaluator = new YrnkEvaluator(new Calendar(), new DateTimeZone('America/New_York'));
        $schedule = $this->schedule(['days' => [8], 'times' => ['02:30']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-03-08T03:30:00-04:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-03-08T01:30:00-05:00')));
    }

    #[Test]
    public function a_wall_time_that_occurs_twice_matches_only_its_first_occurrence(): void
    {
        // 02:00 EDT → 01:00 EST on 2026-11-01, so the wall time 01:30
        // occurs twice. The point counts only as its first occurrence
        // (EDT, -04:00).
        $evaluator = new YrnkEvaluator(new Calendar(), new DateTimeZone('America/New_York'));
        $schedule = $this->schedule(['days' => [1], 'times' => ['01:30']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-11-01T01:30:00-04:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-11-01T01:30:00-05:00')));
    }

    // ---- day expression atoms ----

    #[Test]
    public function an_integer_hits_that_day_of_every_month(): void
    {
        $evaluator = $this->evaluator();
        $schedule = $this->schedule(['days' => [25], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-25T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-08-25T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-24T10:00:00+09:00')));
    }

    #[Test]
    public function the_enumeration_hits_when_any_atom_matches(): void
    {
        $evaluator = $this->evaluator();
        $schedule = $this->schedule(['days' => [5, 'weekday'], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-05T10:00:00+09:00')));  // the 5th (a Sunday)
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-13T10:00:00+09:00')));  // a weekday (Monday)
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-11T10:00:00+09:00'))); // Saturday the 11th
    }

    #[Test]
    public function an_ordinal_tuple_hits_the_nth_weekday_and_misses_in_a_month_without_a_fifth_week(): void
    {
        // 2026-07 has four Mondays: 6, 13, 20, 27.
        $evaluator = $this->evaluator();
        $third = $this->schedule(['days' => [['3rd', 'mon']], 'times' => ['10:00']]);
        $fifth = $this->schedule(['days' => [['5th', 'mon']], 'times' => ['10:00']]);
        $last = $this->schedule(['days' => [['last', 'fri']], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($third, $this->at('2026-07-20T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($third, $this->at('2026-07-13T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($fifth, $this->at('2026-07-27T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($last, $this->at('2026-07-31T10:00:00+09:00')));  // the last of a five-Friday month
        $this->assertFalse($evaluator->matches($last, $this->at('2026-07-24T10:00:00+09:00')));
    }

    #[Test]
    public function the_end_of_month_follows_the_number_of_days_in_the_month(): void
    {
        $evaluator = $this->evaluator();
        $schedule = $this->schedule(['days' => ['last_day_of_month'], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-02-28T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2024-02-29T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2024-02-28T10:00:00+09:00'))); // the 28th of a leap year is not the end of the month
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-04-30T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-31T10:00:00+09:00')));
    }

    #[Test]
    public function hits_the_date_set_of_a_custom_definition(): void
    {
        $evaluator = $this->evaluator(custom: ['founding-day' => ['2026-10-01']]);
        $schedule = $this->schedule(['days' => ['founding-day'], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-10-01T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-10-02T10:00:00+09:00')));
    }

    // ---- the layer model ----

    #[Test]
    public function holidays_are_closed_by_default_and_saturdays_are_closed_by_the_weekly_pattern(): void
    {
        // 2026-01-01 is a Thursday (working by the weekly pattern), but
        // the holiday layer turns it into a day off.
        $evaluator = $this->evaluator(holidays: ['2026-01-01']);
        $businessDay = $this->schedule(['days' => ['business_day'], 'times' => ['10:00']]);
        $businessHoliday = $this->schedule(['days' => ['business_holiday'], 'times' => ['10:00']]);

        $this->assertFalse($evaluator->matches($businessDay, $this->at('2026-01-01T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($businessHoliday, $this->at('2026-01-01T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($businessDay, $this->at('2026-07-11T10:00:00+09:00')));  // Saturday
        $this->assertTrue($evaluator->matches($businessDay, $this->at('2026-07-13T10:00:00+09:00')));   // Monday
    }

    #[Test]
    public function the_business_days_list_overrides_every_other_layer(): void
    {
        // A Saturday that is also a holiday and an organization closure —
        // business_days is still the top layer.
        $evaluator = $this->evaluator(
            holidays: ['2026-07-11'],
            businessHolidays: ['2026-07-11'],
            businessDays: ['2026-07-11'],
        );
        $schedule = $this->schedule(['days' => ['business_day'], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-11T10:00:00+09:00')));
    }

    #[Test]
    public function a_holiday_stays_a_holiday_even_when_business_days_makes_it_a_working_day(): void
    {
        // holiday asks the holidays list alone, independent of the
        // stacked conclusion (business_day).
        $evaluator = $this->evaluator(holidays: ['2026-01-01'], businessDays: ['2026-01-01']);
        $holiday = $this->schedule(['days' => ['holiday'], 'times' => ['10:00']]);
        $businessDay = $this->schedule(['days' => ['business_day'], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($holiday, $this->at('2026-01-01T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($businessDay, $this->at('2026-01-01T10:00:00+09:00')));
    }

    #[Test]
    public function replacing_the_weekly_pattern_changes_the_working_default(): void
    {
        // A shop working Tuesday through Saturday.
        $evaluator = $this->evaluator(workweek: ['tue', 'wed', 'thu', 'fri', 'sat']);
        $schedule = $this->schedule(['days' => ['business_day'], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-11T10:00:00+09:00')));  // Sat
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-13T10:00:00+09:00'))); // Mon
    }

    // ---- shift ----

    #[Test]
    public function payday_falls_to_the_previous_business_day_when_the_25th_is_off(): void
    {
        // 2026-07-25 is a Saturday → 7/24 (Fri). 2026-09-25 is a Friday →
        // stays.
        $evaluator = $this->evaluator();
        $schedule = $this->schedule([
            'days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00'],
        ]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-24T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-25T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-23T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-09-25T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-09-24T10:00:00+09:00')));
    }

    #[Test]
    public function without_or_same_the_shift_moves_strictly_even_when_the_base_day_qualifies(): void
    {
        // 2026-09-25 is a Friday (a business day), yet strict prev
        // excludes itself → the 24th (Thu).
        $evaluator = $this->evaluator();
        $schedule = $this->schedule(['days' => [25], 'shift' => ['prev', 'business_day'], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-09-24T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-09-25T10:00:00+09:00')));
    }

    #[Test]
    public function each_day_of_a_long_weekend_falls_to_the_same_business_day_and_collapses_into_one(): void
    {
        // The three-day weekend 2026-07-18 (Sat), 19 (Sun), 20 (Mon, a
        // holiday) → all fall to 7/17 (Fri).
        $evaluator = $this->evaluator(holidays: ['2026-07-20']);
        $schedule = $this->schedule([
            'days' => ['business_holiday'], 'shift' => ['prev', 'business_day'], 'times' => ['10:00'],
        ]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-17T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-18T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-20T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-21T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-16T10:00:00+09:00')));
    }

    #[Test]
    public function rolling_forward_with_next_can_cross_a_month_boundary(): void
    {
        // 2026-10-31 is a Saturday. next lands on 11/2 (Mon; 11/1 is a
        // Sunday).
        $evaluator = $this->evaluator();
        $schedule = $this->schedule([
            'days' => ['last_day_of_month'], 'shift' => ['next', 'or_same', 'business_day'], 'times' => ['10:00'],
        ]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-11-02T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-10-31T10:00:00+09:00')));
    }

    #[Test]
    public function a_shift_can_land_on_a_leap_day(): void
    {
        // 2024-03-01 is a Friday (a business day). Strict prev excludes
        // itself and lands on 2/29 (Thu). In the common year 2026, 3/1 is
        // a Sunday, and 2/28 (Sat) is skipped too, landing on 2/27 (Fri).
        $evaluator = $this->evaluator();
        $schedule = $this->schedule([
            'months' => [3], 'days' => [1], 'shift' => ['prev', 'business_day'], 'times' => ['10:00'],
        ]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2024-02-29T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2024-02-28T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-02-27T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-02-28T10:00:00+09:00')));
    }

    // ---- if ----

    #[Test]
    public function if_filters_by_the_day_itself_or_a_neighbour(): void
    {
        // Friday the 13th (2026-02-13 is a Friday, 2026-04-13 a Monday),
        // the last working day before a break, and skipping holidays.
        $evaluator = $this->evaluator(holidays: ['2026-07-20']);
        $friday13 = $this->schedule(['days' => [13], 'if' => ['fri'], 'times' => ['10:00']]);
        $beforeRest = $this->schedule(['days' => ['business_day'], 'if' => ['next', 'business_holiday'], 'times' => ['10:00']]);
        $skipHoliday = $this->schedule(['days' => ['mon'], 'if' => ['not', 'holiday'], 'times' => ['10:00']]);

        $this->assertTrue($evaluator->matches($friday13, $this->at('2026-02-13T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($friday13, $this->at('2026-04-13T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($beforeRest, $this->at('2026-07-17T10:00:00+09:00')));  // a Friday followed by a three-day weekend
        $this->assertFalse($evaluator->matches($beforeRest, $this->at('2026-07-15T10:00:00+09:00')));
        $this->assertFalse($evaluator->matches($skipHoliday, $this->at('2026-07-20T10:00:00+09:00'))); // a holiday Monday is skipped (not moved)
        $this->assertTrue($evaluator->matches($skipHoliday, $this->at('2026-07-13T10:00:00+09:00')));
    }

    #[Test]
    public function if_filters_the_base_days_before_shift(): void
    {
        // 2026-07-25 is a Saturday. With if first, July's base day
        // disappears and nothing falls to 7/24.
        $evaluator = $this->evaluator();
        $schedule = $this->schedule([
            'days' => [25], 'if' => ['not', 'sat'], 'shift' => ['prev', 'or_same', 'business_day'],
            'times' => ['10:00'],
        ]);

        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-24T10:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-08-25T10:00:00+09:00'))); // a Tuesday: passes if, a business day, stays
    }

    // ---- the top-level OR and errors ----

    #[Test]
    public function an_undefined_custom_reference_in_a_hand_composed_tree_is_validated_before_evaluation(): void
    {
        $evaluator = $this->evaluator();
        $schedule = $this->schedule(['days' => ['name-defined-nowhere'], 'times' => ['10:00']]);

        $this->expectException(UndefinedNameException::class);

        $evaluator->matches($schedule, $this->at('2026-07-13T10:00:00+09:00'));
    }

    // ---- lazy resolver resolution ----

    #[Test]
    public function a_resolver_is_not_called_at_construction_and_at_most_once_during_evaluation(): void
    {
        $calls = 0;
        $evaluator = new YrnkEvaluator(
            calendar: new Calendar(holidays: Holidays::byResolver('counting')),
            timezone: new DateTimeZone('Asia/Tokyo'),
            resolvers: ['counting' => function () use (&$calls): array {
                $calls++;

                return ['2026-01-01'];
            }],
        );
        $schedule = $this->schedule(['days' => ['holiday'], 'times' => ['10:00']]);

        $this->assertSame(0, $calls);

        $evaluator->matches($schedule, $this->at('2026-01-01T10:00:00+09:00'));
        $evaluator->matches($schedule, $this->at('2026-01-02T10:00:00+09:00'));
        $evaluator->matches($schedule, $this->at('2026-05-05T10:00:00+09:00'));

        $this->assertSame(1, $calls);
    }

    // ---- helpers ----

    /**
     * @param  list<string>  $holidays
     * @param  list<string>  $businessHolidays
     * @param  list<string>  $businessDays
     * @param  list<string>|null  $workweek
     * @param  array<string, list<string>>  $custom
     */
    private function evaluator(
        array $holidays = [],
        array $businessHolidays = [],
        array $businessDays = [],
        ?array $workweek = null,
        array $custom = [],
    ): YrnkEvaluator {
        return new YrnkEvaluator(
            calendar: new Calendar(
                holidays: Holidays::ofDates($holidays),
                businessHolidays: BusinessHolidays::ofDates($businessHolidays),
                businessDays: BusinessDays::ofDates($businessDays),
                workweek: $workweek === null ? null : new Workweek(
                    array_map(static fn(string $day) => DayName::from($day), $workweek),
                ),
                custom: array_map(
                    static fn(array $dates) => CustomDefinition::ofDates($dates),
                    $custom,
                ),
            ),
            timezone: new DateTimeZone('Asia/Tokyo'),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function schedule(array $raw): YrnkSchedule
    {
        return (new ScheduleParser())->parse($raw);
    }

    private function at(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso);
    }
}
