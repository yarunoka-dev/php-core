<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Calendar\Calendar;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\YrnkEvaluator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * from / until — the schedule's validity range. A boundary that clips the
 * set of scheduled points to the half-open interval [from, until),
 * without interfering with how the daily points are laid out.
 */
class FromUntilTest extends TestCase
{
    #[Test]
    public function points_before_from_do_not_exist(): void
    {
        $schedule = (new ScheduleParser())->parse(['from' => '2026-07-14 00:00', 'times' => ['10:00']]);

        $this->assertFalse($this->evaluator()->matches($schedule, $this->at('2026-07-13 10:00:00')));
        $this->assertTrue($this->evaluator()->matches($schedule, $this->at('2026-07-14 10:00:00')));
    }

    #[Test]
    public function a_point_exactly_at_from_is_included(): void
    {
        $schedule = (new ScheduleParser())->parse(['from' => '2026-07-14 10:00', 'times' => ['10:00']]);

        $this->assertTrue($this->evaluator()->matches($schedule, $this->at('2026-07-14 10:00:00')));
    }

    #[Test]
    public function a_from_one_minute_past_the_point_silences_the_first_day(): void
    {
        $schedule = (new ScheduleParser())->parse(['from' => '2026-07-14 10:01', 'times' => ['10:00']]);

        $this->assertFalse($this->evaluator()->matches($schedule, $this->at('2026-07-14 10:00:00')));
        $this->assertTrue($this->evaluator()->matches($schedule, $this->at('2026-07-15 10:00:00')));
    }

    #[Test]
    public function a_point_exactly_at_until_is_excluded(): void
    {
        $schedule = (new ScheduleParser())->parse(['until' => '2026-07-16 10:00', 'times' => ['10:00']]);

        $this->assertTrue($this->evaluator()->matches($schedule, $this->at('2026-07-15 10:00:00')));
        $this->assertFalse($this->evaluator()->matches($schedule, $this->at('2026-07-16 10:00:00')));
    }

    #[Test]
    public function an_until_one_minute_past_the_point_includes_up_to_that_point(): void
    {
        $schedule = (new ScheduleParser())->parse(['until' => '2026-07-16 10:01', 'times' => ['10:00']]);

        $this->assertTrue($this->evaluator()->matches($schedule, $this->at('2026-07-16 10:00:00')));
        $this->assertFalse($this->evaluator()->matches($schedule, $this->at('2026-07-17 10:00:00')));
    }

    #[Test]
    public function has_match_in_does_not_count_points_outside_the_range_either(): void
    {
        $schedule = (new ScheduleParser())->parse([
            'from' => '2026-07-14 00:00',
            'until' => '2026-07-16 00:00',
            'times' => ['10:00'],
        ]);
        $evaluator = $this->evaluator();

        // Before the range (the 7/13 point is before from and does not
        // exist).
        $this->assertFalse($evaluator->hasMatchIn($schedule, $this->at('2026-07-13 00:00:00'), $this->at('2026-07-13 23:59:59')));
        // Inside the range.
        $this->assertTrue($evaluator->hasMatchIn($schedule, $this->at('2026-07-14 00:00:00'), $this->at('2026-07-14 23:59:59')));
        // After the range (the 7/16 point is at or after until and does
        // not exist).
        $this->assertFalse($evaluator->hasMatchIn($schedule, $this->at('2026-07-16 00:00:00'), $this->at('2026-07-16 23:59:59')));
    }

    #[Test]
    public function from_does_not_interfere_with_how_the_grid_is_laid_out(): void
    {
        // The 90-minute grid still anchors at the start of the window
        // (00:00). from only removes points outside the range, so with a
        // 10:00 start the first point is the on-grid 10:30.
        $schedule = (new ScheduleParser())->parse([
            'from' => '2026-07-14 10:00',
            'times' => ['every' => [90, 'minute']],
        ]);
        $evaluator = $this->evaluator();

        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-14 10:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-14 10:30:00')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-14 09:00:00')));
        // The next day starts at 00:00 as always.
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-15 00:00:00')));
    }

    #[Test]
    public function the_allday_point_is_the_start_of_the_day_so_a_later_from_clips_that_day(): void
    {
        $schedule = (new ScheduleParser())->parse(['from' => '2026-07-14 12:00', 'allday' => true]);
        $evaluator = $this->evaluator();

        // The 7/14 allday point (00:00) is before from and does not
        // exist.
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-14 15:00:00')));
        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-15 15:00:00')));
    }

    #[Test]
    public function both_from_and_until_are_optional_for_a_schedule_that_does_not_count(): void
    {
        $schedule = (new ScheduleParser())->parse(['days' => ['mon'], 'times' => ['10:00']]);

        // 2026-07-13 is a Monday.
        $this->assertTrue($this->evaluator()->matches($schedule, $this->at('2026-07-13 10:00:00')));
    }

    // ---- helpers ----

    private function evaluator(): YrnkEvaluator
    {
        return new YrnkEvaluator(calendar: new Calendar(), timezone: new DateTimeZone('Asia/Tokyo'));
    }

    private function at(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('Asia/Tokyo'));
    }
}
