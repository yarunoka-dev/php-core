<?php

namespace Yarunoka\Tests\Feature\Scenario;

use Yarunoka\Calendar\BusinessDays;
use Yarunoka\Calendar\BusinessHolidays;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Calendar\Holidays;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\Tests\Support\RoutinePoller;
use Yarunoka\YrnkEvaluator;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Scenario tests of poller operation. Verifies firing counts and timing
 * as the story of a caller (RoutinePoller) advancing through time while
 * questioning the evaluator.
 */
class PollerScenarioTest extends TestCase
{
    #[Test]
    public function a_routine_at_8_every_morning_fires_exactly_once_a_day(): void
    {
        $poller = $this->poller(['times' => ['08:00']], '2026-06-29T07:58:00+09:00');

        $this->assertFalse($poller->tick($this->at('2026-06-29T07:59:00+09:00')));
        $this->assertTrue($poller->tick($this->at('2026-06-29T08:00:00+09:00')));
        $this->assertFalse($poller->tick($this->at('2026-06-29T08:01:00+09:00')));
        $this->assertFalse($poller->tick($this->at('2026-06-29T23:59:00+09:00')));
        $this->assertTrue($poller->tick($this->at('2026-06-30T08:00:30+09:00')));
    }

    #[Test]
    public function a_point_missed_during_downtime_fires_exactly_once_on_the_first_tick_after_recovery(): void
    {
        $poller = $this->poller(['times' => ['08:00']], '2026-06-29T07:00:00+09:00');

        $this->assertFalse($poller->tick($this->at('2026-06-29T07:30:00+09:00')));
        // --- downtime ---
        $this->assertTrue($poller->tick($this->at('2026-06-29T10:30:00+09:00')));
        $this->assertFalse($poller->tick($this->at('2026-06-29T10:31:00+09:00')));
    }

    #[Test]
    public function several_days_of_misses_still_collapse_into_one_firing(): void
    {
        $poller = $this->poller(['times' => ['08:00']], '2026-06-29T09:00:00+09:00');

        $this->assertTrue($poller->tick($this->at('2026-07-02T12:00:00+09:00')));
        $this->assertFalse($poller->tick($this->at('2026-07-02T12:01:00+09:00')));
        $this->assertTrue($poller->tick($this->at('2026-07-03T08:00:00+09:00')));
    }

    #[Test]
    public function a_poller_with_grace_lets_old_misses_flow_past(): void
    {
        $poller = $this->poller(
            ['times' => ['08:00']],
            '2026-06-28T22:00:00+09:00',
            grace: new DateInterval('PT1H'),
        );

        $this->assertFalse($poller->tick($this->at('2026-06-29T10:30:00+09:00')));
        $this->assertTrue($poller->tick($this->at('2026-06-30T08:59:00+09:00')));
    }

    #[Test]
    public function recovering_outside_the_time_window_does_not_catch_up(): void
    {
        $poller = $this->poller([
            'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'times' => ['every' => [1, 'hour'], 'between' => ['08:00', '20:00']],
        ], '2026-07-10T19:00:00+09:00');

        $this->assertFalse($poller->tick($this->at('2026-07-10T20:30:00+09:00')));
        $this->assertFalse($poller->tick($this->at('2026-07-10T23:59:00+09:00')));
        $this->assertTrue($poller->tick($this->at('2026-07-13T08:00:00+09:00')));
    }

    #[Test]
    public function allday_fires_on_the_first_tick_after_the_date_changes_and_never_twice_a_day(): void
    {
        $poller = $this->poller(['days' => [['3rd', 'mon']], 'allday' => true], '2026-07-19T23:59:00+09:00');

        $this->assertTrue($poller->tick($this->at('2026-07-20T00:00:30+09:00')));
        $this->assertFalse($poller->tick($this->at('2026-07-20T08:00:00+09:00')));
        $this->assertFalse($poller->tick($this->at('2026-07-20T23:59:00+09:00')));
    }

    #[Test]
    public function the_resolver_resolves_once_however_many_days_are_polled(): void
    {
        $calls = 0;
        $evaluator = new YrnkEvaluator(
            calendar: new Calendar(
                holidays: Holidays::byResolver('db-holidays'),
                businessHolidays: BusinessHolidays::ofDates([]),
                businessDays: BusinessDays::ofDates([]),
            ),
            timezone: new DateTimeZone('Asia/Tokyo'),
            resolvers: ['db-holidays' => function () use (&$calls): array {
                $calls++;

                return ['2026-07-20'];
            }],
        );
        $poller = new RoutinePoller(
            $evaluator,
            (new ScheduleParser())->parse(['days' => ['business_day'], 'times' => ['08:00']]),
            $this->at('2026-07-16T00:00:00+09:00'),
        );

        $this->assertSame(0, $calls);

        $poller->tick($this->at('2026-07-16T08:00:00+09:00'));
        $poller->tick($this->at('2026-07-17T08:00:00+09:00'));
        $poller->tick($this->at('2026-07-21T08:00:00+09:00'));

        $this->assertSame(1, $calls);
    }

    // ---- helpers ----

    /**
     * @param  array<string, mixed>  $schedule
     */
    private function poller(array $schedule, string $startedAt, ?DateInterval $grace = null): RoutinePoller
    {
        $evaluator = new YrnkEvaluator(
            calendar: new Calendar(
                holidays: Holidays::ofDates([]),
                businessHolidays: BusinessHolidays::ofDates([]),
                businessDays: BusinessDays::ofDates([]),
            ),
            timezone: new DateTimeZone('Asia/Tokyo'),
        );

        return new RoutinePoller($evaluator, (new ScheduleParser())->parse($schedule), $this->at($startedAt), $grace);
    }

    private function at(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso);
    }
}
