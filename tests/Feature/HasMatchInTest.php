<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Definitions\BusinessDays;
use Yarunoka\Definitions\BusinessHolidays;
use Yarunoka\Definitions\Definitions;
use Yarunoka\Definitions\Holidays;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cross-checks of hasMatchIn (the interval check): the worked examples of
 * the design report, catch-up question patterns, interval boundaries, and
 * timezone boundaries. "The next point is here" is verified as a boundary
 * pair cutting to just before the point (yes up to the point itself, no
 * up to one second before it).
 */
class HasMatchInTest extends TestCase
{
    // ---- worked examples ----

    #[Test]
    public function the_third_monday_of_every_month_at_ten(): void
    {
        $schedule = $this->schedule(['days' => [['3rd', 'mon']], 'times' => ['10:00']]);

        $this->assertFirstMatchAfter('2026-07-20T10:00:00+09:00', $schedule, '2026-07-01T00:00:00+09:00');
        // A point exactly at from does not count → the next is next
        // month's third Monday.
        $this->assertFirstMatchAfter('2026-08-17T10:00:00+09:00', $schedule, '2026-07-20T10:00:00+09:00');
    }

    #[Test]
    public function hourly_on_weekdays_between_8_and_20_never_rings_at_20_being_half_open(): void
    {
        $schedule = $this->schedule([
            'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'times' => ['every' => [1, 'hour'], 'between' => ['08:00', '20:00']],
        ]);

        $this->assertFirstMatchAfter('2026-07-10T19:00:00+09:00', $schedule, '2026-07-10T18:30:00+09:00');
        $this->assertFirstMatchAfter('2026-07-13T08:00:00+09:00', $schedule, '2026-07-10T19:00:00+09:00'); // skips the weekend
    }

    #[Test]
    public function eight_oclock_of_the_last_business_day_before_a_break(): void
    {
        $schedule = $this->schedule([
            'days' => ['business_day'], 'if' => ['next', 'business_holiday'], 'times' => ['08:00'],
        ]);

        $this->assertFirstMatchAfter('2026-07-17T08:00:00+09:00', $schedule, '2026-07-14T00:00:00+09:00');
    }

    #[Test]
    public function every_600_seconds_is_a_clock_grid(): void
    {
        $schedule = $this->schedule(['times' => ['every' => [600, 'second']]]);

        $this->assertFirstMatchAfter('2026-07-12T12:10:00+09:00', $schedule, '2026-07-12T12:03:00+09:00');
        $this->assertFirstMatchAfter('2026-07-13T00:00:00+09:00', $schedule, '2026-07-12T23:50:00+09:00');
    }

    // ---- additional cross-checks ----

    #[Test]
    public function payday_month_end_business_day_and_a_golden_wedding(): void
    {
        $payday = $this->schedule(['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']]);
        $monthEnd = $this->schedule(['days' => ['last_day_of_month'], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['17:00']]);
        $anniversary = $this->schedule(['years' => [2043], 'months' => [6], 'days' => [15], 'allday' => true]);

        $this->assertFirstMatchAfter('2026-07-24T10:00:00+09:00', $payday, '2026-07-01T00:00:00+09:00');
        $this->assertFirstMatchAfter('2026-10-30T17:00:00+09:00', $monthEnd, '2026-10-01T00:00:00+09:00'); // 10/31 is a Saturday

        // The allday point stands at the start of the day. However many
        // years ahead, candidate-month narrowing answers it.
        $this->assertFirstMatchAfter('2043-06-15T00:00:00+09:00', $anniversary, '2026-07-12T00:00:00+09:00');
    }

    #[Test]
    public function a_one_off_event_in_the_past_never_appears_in_future_intervals(): void
    {
        $schedule = $this->schedule(['years' => [2020], 'months' => [6], 'days' => [15], 'allday' => true]);

        $this->assertFalse($this->evaluator()->hasMatchIn(
            $schedule,
            $this->at('2026-07-12T00:00:00+09:00'),
            $this->at('2038-01-01T00:00:00+09:00'),
        ));
    }

    #[Test]
    public function a_direct_debit_on_the_27th_of_even_months_rolls_forward_on_days_off(): void
    {
        $schedule = $this->schedule([
            'months' => [2, 4, 6, 8, 10, 12], 'days' => [27],
            'shift' => ['next', 'or_same', 'business_day'], 'times' => ['09:00'],
        ]);

        $this->assertFirstMatchAfter('2026-08-27T09:00:00+09:00', $schedule, '2026-07-01T00:00:00+09:00');
        $this->assertFirstMatchAfter('2026-12-28T09:00:00+09:00', $schedule, '2026-12-01T00:00:00+09:00'); // 12/27 is a Sunday
    }

    #[Test]
    public function the_allday_point_stands_at_the_start_of_the_shift_landing_day(): void
    {
        // 2026-07-25 is a Saturday → lands on 7/24 (Fri), and the point is
        // the single one at 7/24 00:00.
        $schedule = $this->schedule([
            'days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'allday' => true,
        ]);

        $this->assertFirstMatchAfter('2026-07-24T00:00:00+09:00', $schedule, '2026-07-23T23:00:00+09:00');
    }

    #[Test]
    public function the_fifth_monday_skips_months_without_one_and_hits_the_next_month_that_has_it(): void
    {
        $schedule = $this->schedule(['days' => [['5th', 'mon']], 'times' => ['09:00']]);

        $this->assertFirstMatchAfter('2026-08-31T09:00:00+09:00', $schedule, '2026-07-01T00:00:00+09:00');
    }

    #[Test]
    public function a_leap_day_schedule_answers_years_ahead_without_a_horizon(): void
    {
        // 2/29 can be up to 8 years apart across a century boundary (after
        // 2096 comes 2104; 2100 is not a leap year). The old
        // implementation's 12-year search horizon guarded this case; the
        // hierarchical evaluation reaches it by candidate-month narrowing
        // alone.
        $schedule = $this->schedule(['months' => [2], 'days' => [29], 'times' => ['10:00']]);

        $this->assertFirstMatchAfter('2028-02-29T10:00:00+09:00', $schedule, '2025-01-01T00:00:00+09:00');
        $this->assertFirstMatchAfter('2104-02-29T10:00:00+09:00', $schedule, '2096-03-01T00:00:00+09:00');
    }

    // ---- catch-up (the caller's question patterns) ----

    #[Test]
    public function a_missed_scheduled_point_is_picked_up_by_an_interval_question(): void
    {
        $schedule = $this->schedule(['days' => [['3rd', 'mon']], 'times' => ['10:00']]);

        $this->assertTrue($this->evaluator()->hasMatchIn(
            $schedule,
            $this->at('2026-07-19T23:00:00+09:00'),
            $this->at('2026-07-20T13:00:00+09:00'),
        ));
    }

    #[Test]
    public function grace_trimming_only_raises_the_lower_bound_of_the_question_interval(): void
    {
        // With from = max(last_run_at, now - grace) = 12:00, the 10:00
        // point is out of the interval.
        $schedule = $this->schedule(['days' => [['3rd', 'mon']], 'times' => ['10:00']]);

        $this->assertFalse($this->evaluator()->hasMatchIn(
            $schedule,
            $this->at('2026-07-20T12:00:00+09:00'),
            $this->at('2026-07-20T13:00:00+09:00'),
        ));
    }

    #[Test]
    public function detecting_a_time_outside_between_is_not_caught_up(): void
    {
        // There is no 20:00 scheduled point in the first place (the
        // half-open interval).
        $schedule = $this->schedule([
            'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'times' => ['every' => [1, 'hour'], 'between' => ['08:00', '20:00']],
        ]);

        $this->assertFalse($this->evaluator()->hasMatchIn(
            $schedule,
            $this->at('2026-07-10T19:30:00+09:00'),
            $this->at('2026-07-10T20:30:00+09:00'),
        ));
    }

    // ---- the boundaries of the question interval (from, to] ----

    #[Test]
    public function a_point_exactly_at_the_lower_bound_is_not_counted_and_one_at_the_upper_bound_is(): void
    {
        $schedule = $this->schedule(['times' => ['09:00']]);
        $evaluator = $this->evaluator();

        $this->assertFalse($evaluator->hasMatchIn(
            $schedule,
            $this->at('2026-07-12T09:00:00+09:00'),
            $this->at('2026-07-12T09:30:00+09:00'),
        ));
        $this->assertTrue($evaluator->hasMatchIn(
            $schedule,
            $this->at('2026-07-12T08:30:00+09:00'),
            $this->at('2026-07-12T09:00:00+09:00'),
        ));
        $this->assertFalse($evaluator->hasMatchIn(
            $schedule,
            $this->at('2026-07-12T09:00:00+09:00'),
            $this->at('2026-07-12T09:00:00+09:00'),
        ));
    }

    // ---- timezones ----

    #[Test]
    public function question_times_are_absolute_instants_whatever_timezone_they_are_passed_in(): void
    {
        // 16:00 UTC on 7/19 = 01:00 JST on 7/20 → the next is 10:00 JST of
        // the third Monday (7/20).
        $schedule = $this->schedule(['days' => [['3rd', 'mon']], 'times' => ['10:00']]);
        $from = new DateTimeImmutable('2026-07-19 16:00:00', new DateTimeZone('UTC'));
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->hasMatchIn($schedule, $from, $this->at('2026-07-20T10:00:00+09:00')));
        $this->assertFalse($evaluator->hasMatchIn($schedule, $from, $this->at('2026-07-20T09:59:59+09:00')));
    }

    #[Test]
    public function the_configured_timezone_decides_the_day_boundary(): void
    {
        // "Every day at 0:00" configured for UTC. 20:00 JST on 7/12 =
        // 11:00 UTC on 7/12 → the next is 0:00 UTC on 7/13.
        $evaluator = new YrnkEvaluator(definitions: new Definitions, timezone: new DateTimeZone('UTC'));
        $schedule = $this->schedule(['times' => ['00:00']]);
        $from = $this->at('2026-07-12T20:00:00+09:00');

        $this->assertTrue($evaluator->hasMatchIn($schedule, $from, $this->at('2026-07-13T00:00:00+00:00')));
        $this->assertFalse($evaluator->hasMatchIn($schedule, $from, $this->at('2026-07-12T23:59:59+00:00')));
    }

    #[Test]
    public function a_negative_offset_timezone_still_matches_by_the_wall_date(): void
    {
        // America/Phoenix is -07:00 without DST. The 22:00 point of the
        // 15th is 14:00 JST of the next day, the 16th.
        $evaluator = new YrnkEvaluator(definitions: new Definitions, timezone: new DateTimeZone('America/Phoenix'));
        $schedule = $this->schedule(['days' => [15], 'times' => ['22:00']]);
        $from = $this->at('2026-07-16T00:00:00+09:00');

        $this->assertTrue($evaluator->hasMatchIn($schedule, $from, $this->at('2026-07-16T14:00:00+09:00')));
        $this->assertFalse($evaluator->hasMatchIn($schedule, $from, $this->at('2026-07-16T13:59:59+09:00')));
    }

    #[Test]
    public function a_fractional_offset_timezone_stands_its_points_correctly(): void
    {
        // Asia/Kathmandu is +05:45. The 9:00 point is 12:15 JST.
        $evaluator = new YrnkEvaluator(definitions: new Definitions, timezone: new DateTimeZone('Asia/Kathmandu'));
        $schedule = $this->schedule(['days' => [15], 'times' => ['09:00']]);
        $from = $this->at('2026-07-15T12:00:00+09:00');

        $this->assertTrue($evaluator->hasMatchIn($schedule, $from, $this->at('2026-07-15T12:15:00+09:00')));
        $this->assertFalse($evaluator->hasMatchIn($schedule, $from, $this->at('2026-07-15T12:14:59+09:00')));
    }

    // ---- DST transitions (RFC 5545 §3.3.5) ----

    #[Test]
    public function a_point_at_a_nonexistent_wall_time_is_pushed_forward(): void
    {
        // America/New_York transitions 02:00 EST → 03:00 EDT on
        // 2026-03-08. The 02:30 point does not vanish; it stands at the
        // instant 03:30 EDT.
        $evaluator = new YrnkEvaluator(new Definitions, new DateTimeZone('America/New_York'));
        $schedule = $this->schedule(['days' => [8], 'times' => ['02:30']]);
        $from = $this->at('2026-03-08T00:00:00-05:00');

        $this->assertTrue($evaluator->hasMatchIn($schedule, $from, $this->at('2026-03-08T03:30:00-04:00')));
        $this->assertFalse($evaluator->hasMatchIn($schedule, $from, $this->at('2026-03-08T03:29:59-04:00')));
    }

    #[Test]
    public function a_point_at_a_wall_time_that_occurs_twice_stands_only_at_its_first_occurrence(): void
    {
        // 02:00 EDT → 01:00 EST on 2026-11-01. The 01:30 point is the
        // single EDT one and does not stand at the second 01:30 (EST) an
        // hour later.
        $evaluator = new YrnkEvaluator(new Definitions, new DateTimeZone('America/New_York'));
        $schedule = $this->schedule(['days' => [1], 'times' => ['01:30']]);
        $from = $this->at('2026-11-01T00:00:00-04:00');

        $this->assertTrue($evaluator->hasMatchIn($schedule, $from, $this->at('2026-11-01T01:30:00-04:00')));
        $this->assertFalse($evaluator->hasMatchIn($schedule, $from, $this->at('2026-11-01T01:29:59-04:00')));
        // There is no point in (the first occurrence, the second wall
        // 01:30].
        $this->assertFalse($evaluator->hasMatchIn(
            $schedule, $this->at('2026-11-01T01:30:00-04:00'), $this->at('2026-11-01T01:30:00-05:00'),
        ));
    }

    #[Test]
    public function the_every_grid_stands_no_points_in_the_second_pass_of_a_25_hour_day(): void
    {
        // The wall-clock grid stays at 24 points a day. The 01:00 hour
        // coming around again after the fall-back has no points; the next
        // point is at wall 02:00 (EST).
        $evaluator = new YrnkEvaluator(new Definitions, new DateTimeZone('America/New_York'));
        $schedule = $this->schedule(['days' => [1], 'times' => ['every' => [1, 'hour']]]);

        $this->assertFalse($evaluator->hasMatchIn(
            $schedule,
            $this->at('2026-11-01T01:00:00-04:00'),
            $this->at('2026-11-01T01:59:59-05:00'),
        ));
        $this->assertTrue($evaluator->hasMatchIn(
            $schedule,
            $this->at('2026-11-01T01:00:00-04:00'),
            $this->at('2026-11-01T02:00:00-05:00'),
        ));
    }

    #[Test]
    public function wall_times_folded_together_by_the_gap_are_one_point_at_the_same_instant(): void
    {
        // Wall 02:00 is pushed to 03:00 and 02:30 to 03:30, coinciding
        // with the wall 03:00 / 03:30 points at the same instants. The
        // result is a set, so no separate point remains at the same
        // moment.
        $evaluator = new YrnkEvaluator(new Definitions, new DateTimeZone('America/New_York'));
        $schedule = $this->schedule(['days' => [8], 'times' => ['every' => [30, 'minute']]]);

        $this->assertTrue($evaluator->hasMatchIn(
            $schedule,
            $this->at('2026-03-08T01:59:59-05:00'),
            $this->at('2026-03-08T03:00:00-04:00'),
        ));
        $this->assertFalse($evaluator->hasMatchIn(
            $schedule,
            $this->at('2026-03-08T03:00:00-04:00'),
            $this->at('2026-03-08T03:29:59-04:00'),
        ));
        $this->assertTrue($evaluator->hasMatchIn(
            $schedule,
            $this->at('2026-03-08T03:00:00-04:00'),
            $this->at('2026-03-08T03:30:00-04:00'),
        ));
    }

    // ---- helpers ----

    private function evaluator(): YrnkEvaluator
    {
        return new YrnkEvaluator(
            definitions: new Definitions(
                holidays: Holidays::ofDates([]),
                businessHolidays: BusinessHolidays::ofDates([]),
                businessDays: BusinessDays::ofDates([]),
            ),
            timezone: new DateTimeZone('Asia/Tokyo'),
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

    /**
     * Verifies with a boundary pair that the first point after from is
     * expected: there is a point in (from, expected], and none in
     * (from, expected - 1 second].
     */
    private function assertFirstMatchAfter(string $expectedIso, YrnkSchedule $schedule, string $fromIso): void
    {
        $evaluator = $this->evaluator();
        $expected = $this->at($expectedIso);

        $this->assertTrue($evaluator->hasMatchIn($schedule, $this->at($fromIso), $expected));
        $this->assertFalse($evaluator->hasMatchIn($schedule, $this->at($fromIso), $expected->modify('-1 second')));
    }
}
