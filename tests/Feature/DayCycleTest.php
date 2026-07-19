<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Definitions\Definitions;
use Yarunoka\Definitions\Holidays;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\YrnkEvaluator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ["every", N, "day"] — the calendar day cycle of every N days. The
 * matching days are every Nth day counting the date of the schedule's
 * from as day one. The firing times are decided by times.
 */
class DayCycleTest extends TestCase
{
    #[Test]
    public function every_other_day_rings_counting_the_from_day_as_day_one(): void
    {
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-14 00:00',
            'days' => [['every', 2, 'day']],
            'times' => ['03:00'],
        ]);
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-14 03:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-15 03:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-16 03:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-13 03:00:00')));
    }

    #[Test]
    public function the_time_of_from_only_clips_the_first_days_point_without_shifting_the_count(): void
    {
        // Day one of the count is the from date (7/14). The 03:00 of 7/14
        // is before from and does not exist, but the phase stays anchored
        // at 7/14.
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-14 12:00',
            'days' => [['every', 2, 'day']],
            'times' => ['03:00'],
        ]);
        $evaluator = $this->evaluator();

        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-14 03:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-15 03:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-16 03:00:00')));
    }

    #[Test]
    public function the_phase_is_kept_across_a_month_boundary(): void
    {
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-24 00:00',
            'days' => [['every', 3, 'day']],
            'times' => ['10:00'],
        ]);
        $evaluator = $this->evaluator();

        // 7/24, 7/27, 7/30, 8/2, 8/5, …
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-30 10:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-31 10:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-08-01 10:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-08-02 10:00:00')));
    }

    #[Test]
    public function a_matching_day_can_carry_several_times(): void
    {
        // Medication every other day, morning and evening.
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-14 00:00',
            'days' => [['every', 2, 'day']],
            'times' => ['08:00', '20:00'],
        ]);
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-14 08:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-14 20:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-15 08:00:00')));
    }

    #[Test]
    public function months_only_filters_the_matching_days_and_does_not_reset_the_count(): void
    {
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-14 00:00',
            'months' => [8],
            'days' => [['every', 2, 'day']],
            'times' => ['10:00'],
        ]);
        $evaluator = $this->evaluator();

        // July's matching days are removed by months.
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-16 10:00:00')));
        // 8/1 is 18 days (even) from from — the phase stays anchored at
        // from.
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-08-01 10:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-08-02 10:00:00')));
    }

    #[Test]
    public function days_excluded_by_if_do_not_shift_the_cycle(): void
    {
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-14 00:00',
            'days' => [['every', 2, 'day']],
            'if' => ['not', 'holiday'],
            'times' => ['10:00'],
        ]);
        $evaluator = $this->evaluator(holidays: ['2026-07-20']);

        // 7/20 (a matching day, but a holiday) is skipped. The next stays
        // unshifted at 7/22.
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-20 10:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-21 10:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-22 10:00:00')));
    }

    #[Test]
    public function every_1_day_is_every_day_from_the_from_date(): void
    {
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-14 00:00',
            'days' => [['every', 1, 'day']],
            'times' => ['10:00'],
        ]);
        $evaluator = $this->evaluator();

        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-13 10:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-14 10:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-15 10:00:00')));
    }

    #[Test]
    public function combining_with_other_atoms_is_an_or(): void
    {
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-14 00:00',
            'days' => [['every', 2, 'day'], 'mon'],
            'times' => ['10:00'],
        ]);
        $evaluator = $this->evaluator();

        // 7/27 is a Monday (not a cycle day: 13 days from from).
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-27 10:00:00')));
        // 7/16 is a cycle day (not a Monday).
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-16 10:00:00')));
        // 7/15 is neither.
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-15 10:00:00')));
    }

    #[Test]
    public function the_cycle_stops_at_until(): void
    {
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-14 00:00',
            'until' => '2026-07-18 03:00',
            'days' => [['every', 2, 'day']],
            'times' => ['03:00'],
        ]);
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-16 03:00:00')));
        // A point exactly at until is outside the half-open interval.
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-18 03:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-20 03:00:00')));
    }

    #[Test]
    public function has_match_in_does_not_count_non_matching_days(): void
    {
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-14 00:00',
            'days' => [['every', 2, 'day']],
            'times' => ['03:00'],
        ]);
        $evaluator = $this->evaluator();

        // (7/14 04:00, 7/16 03:00] contains 7/16 03:00.
        $this->assertTrue($evaluator->hasMatchIn($schedule, $this->at('2026-07-14 04:00:00'), $this->at('2026-07-16 03:00:00')));
        // (7/14 04:00, 7/15 23:00] has no matching-day points.
        $this->assertFalse($evaluator->hasMatchIn($schedule, $this->at('2026-07-14 04:00:00'), $this->at('2026-07-15 23:00:00')));
    }

    // ---- helpers ----

    /**
     * @param  list<string>  $holidays
     */
    private function evaluator(array $holidays = []): YrnkEvaluator
    {
        return new YrnkEvaluator(
            definitions: new Definitions(
                holidays: $holidays === [] ? null : Holidays::ofDates($holidays),
            ),
            timezone: new DateTimeZone('Asia/Tokyo'),
        );
    }

    private function at(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('Asia/Tokyo'));
    }
}
