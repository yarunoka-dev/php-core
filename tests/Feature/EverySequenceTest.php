<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Definitions\Definitions;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\YrnkEvaluator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The interval every — the from-anchored sequence of points (from + k ×
 * interval). It keeps counting across days, with none of the clock
 * grid's per-day re-anchoring.
 */
class EverySequenceTest extends TestCase
{
    #[Test]
    public function from_is_the_first_point(): void
    {
        $schedule = (new ScheduleParser)->parse(['from' => '2026-07-17 10:00', 'every' => [7, 'hour']]);
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-17 10:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-17 09:59:59')));
    }

    #[Test]
    public function the_count_continues_across_days(): void
    {
        // Every 7 hours anchored at 7/17 10:00: 10:00, 17:00, 00:00 the
        // next day, 07:00, 14:00, …
        $schedule = (new ScheduleParser)->parse(['from' => '2026-07-17 10:00', 'every' => [7, 'hour']]);
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-17 17:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-18 00:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-18 07:00:00')));
        // "10:00 the next day", which per-day re-anchoring would produce,
        // is not a point.
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-18 10:00:00')));
    }

    #[Test]
    public function the_motivating_172800_seconds_resolve_as_an_every_two_days_sequence(): void
    {
        $schedule = (new ScheduleParser)->parse(['from' => '2026-07-14 03:00', 'every' => [172800, 'second']]);
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-14 03:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-15 03:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-16 03:00:00')));
    }

    #[Test]
    public function every_36_hours_which_decomposes_into_no_days_and_times_is_writable(): void
    {
        $schedule = (new ScheduleParser)->parse(['from' => '2026-07-14 00:00', 'every' => [36, 'hour']]);
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-14 00:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-15 12:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-17 00:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-16 00:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-14 12:00:00')));
    }

    #[Test]
    public function the_sequence_stops_at_until(): void
    {
        $schedule = (new ScheduleParser)->parse([
            'from' => '2026-07-14 00:00',
            'until' => '2026-07-17 00:00',
            'every' => [36, 'hour'],
        ]);
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-15 12:00:00')));
        // A point exactly at until is outside the half-open interval.
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-17 00:00:00')));
    }

    #[Test]
    public function the_firing_decision_is_asked_as_the_interval_between_last_run_and_now(): void
    {
        $schedule = (new ScheduleParser)->parse(['from' => '2026-07-14 00:00', 'every' => [36, 'hour']]);
        $evaluator = $this->evaluator();

        // Last run 7/14 00:30, now 7/15 12:00 → the 7/15 12:00 point is
        // there.
        $this->assertTrue($evaluator->hasMatchIn($schedule, $this->at('2026-07-14 00:30:00'), $this->at('2026-07-15 12:00:00')));
        // Last run 7/15 12:30, now 7/16 23:00 → the next point (7/17
        // 00:00) has not come yet.
        $this->assertFalse($evaluator->hasMatchIn($schedule, $this->at('2026-07-15 12:30:00'), $this->at('2026-07-16 23:00:00')));
        // No points in an interval before from.
        $this->assertFalse($evaluator->hasMatchIn($schedule, $this->at('2026-07-10 00:00:00'), $this->at('2026-07-13 23:59:59')));
        // The interval's upper bound exactly at the first point (from).
        $this->assertTrue($evaluator->hasMatchIn($schedule, $this->at('2026-07-13 00:00:00'), $this->at('2026-07-14 00:00:00')));
    }

    #[Test]
    public function minute_and_second_units_yield_the_same_kind_of_sequence(): void
    {
        // Every 90 minutes: 10:00, 11:30, 13:00, … (unlike the grid it
        // keeps counting across days).
        $schedule = (new ScheduleParser)->parse(['from' => '2026-07-14 10:00', 'every' => [90, 'minute']]);
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-14 11:30:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-14 23:30:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-15 01:00:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-15 00:00:00')));
    }

    #[Test]
    public function a_point_falling_into_a_dst_gap_is_pushed_forward(): void
    {
        // America/New_York has the spring transition 02:00 → 03:00 on
        // 2026-03-08. In an hourly sequence anchored at 3/7 01:30, the
        // wall 02:30 of 3/8 does not exist and is pushed to 03:30.
        $timezone = new DateTimeZone('America/New_York');
        $evaluator = new YrnkEvaluator(definitions: new Definitions, timezone: $timezone);
        $schedule = (new ScheduleParser)->parse(['from' => '2026-03-08 01:30', 'every' => [1, 'hour']]);

        $this->assertTrue($evaluator->matches($schedule, new DateTimeImmutable('2026-03-08 01:30:00', $timezone)));
        // The wall 02:30 point is pushed to the instant 03:30 EDT (the
        // same single point as the wall 03:30 one).
        $this->assertTrue($evaluator->matches($schedule, new DateTimeImmutable('2026-03-08 03:30:00', $timezone)));
        $this->assertTrue($evaluator->matches($schedule, new DateTimeImmutable('2026-03-08 04:30:00', $timezone)));
    }

    // ---- helpers ----

    private function evaluator(): YrnkEvaluator
    {
        return new YrnkEvaluator(definitions: new Definitions, timezone: new DateTimeZone('Asia/Tokyo'));
    }

    private function at(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('Asia/Tokyo'));
    }
}
