<?php

namespace Yarunoka\Tests\Feature\Scenario;

use Yarunoka\Calendar\BusinessDays;
use Yarunoka\Calendar\BusinessHolidays;
use Yarunoka\Calendar\BusinessHours;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Calendar\Holidays;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\Time\TimeWindow;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Scenario tests enumerating the firing days of a year against the
 * actual 2026 calendar (Japanese public holidays). Verifies "when does
 * this routine ring this year" as a story, rather than individual grammar
 * elements.
 */
class CalendarScenarioTest extends TestCase
{
    /** The Japanese public holidays of 2026 (including substitute and citizens' holidays) */
    private const array JAPANESE_HOLIDAYS_2026 = [
        '2026-01-01', // New Year's Day
        '2026-01-12', // Coming of Age Day
        '2026-02-11', // National Foundation Day
        '2026-02-23', // The Emperor's Birthday
        '2026-03-20', // Vernal Equinox Day
        '2026-04-29', // Showa Day
        '2026-05-03', // Constitution Memorial Day
        '2026-05-04', // Greenery Day
        '2026-05-05', // Children's Day
        '2026-05-06', // substitute holiday (5/3 falls on a Sunday)
        '2026-07-20', // Marine Day
        '2026-08-11', // Mountain Day
        '2026-09-21', // Respect for the Aged Day
        '2026-09-22', // citizens' holiday (the weekday between the two around it)
        '2026-09-23', // Autumnal Equinox Day
        '2026-10-12', // Sports Day
        '2026-11-03', // Culture Day
        '2026-11-23', // Labour Thanksgiving Day
    ];

    #[Test]
    public function the_2026_firing_days_of_the_payday_routine(): void
    {
        // The 25th of every month, moved earlier on weekends and
        // holidays. In 2026 the move happens in four months: 1/25 (Sun),
        // 4/25 (Sat), 7/25 (Sat), and 10/25 (Sun).
        $schedule = $this->schedule(
            ['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']],
        );

        $this->assertSame([
            '2026-01-23',
            '2026-02-25',
            '2026-03-25',
            '2026-04-24',
            '2026-05-25',
            '2026-06-25',
            '2026-07-24',
            '2026-08-25',
            '2026-09-25',
            '2026-10-23',
            '2026-11-25',
            '2026-12-25',
        ], $this->firingDates($schedule, '2026-01-01', '2026-12-31'));
    }

    #[Test]
    public function the_first_half_2026_firing_days_of_the_garbage_collection_routine(): void
    {
        // The 1st and 3rd Friday, skipped on holidays (not moved). The
        // third Friday of March, 3/20, coincides with the Vernal Equinox
        // and one firing is skipped.
        $schedule = $this->schedule(
            ['days' => [['1st', 'fri'], ['3rd', 'fri']], 'if' => ['not', 'holiday'], 'times' => ['07:30']],
        );

        $this->assertSame([
            '2026-01-02', '2026-01-16',
            '2026-02-06', '2026-02-20',
            '2026-03-06',
            '2026-04-03', '2026-04-17',
            '2026-05-01', '2026-05-15',
            '2026-06-05', '2026-06-19',
        ], $this->firingDates($schedule, '2026-01-01', '2026-06-30'));
    }

    #[Test]
    public function the_day_before_a_break_routine_rings_before_golden_week(): void
    {
        // Golden Week 2026 is the five days off from 5/2 (Sat) through
        // 5/6 (the substitute holiday). The business day before it is 5/1
        // (Fri). 4/28 (Tue), the day before Showa Day (4/29, Wed), also
        // rings as "off from tomorrow".
        $schedule = $this->schedule(
            ['days' => ['business_day'], 'if' => ['next', 'business_holiday'], 'times' => ['08:00']],
        );

        $this->assertSame([
            '2026-04-24',
            '2026-04-28',
            '2026-05-01',
            '2026-05-08',
        ], $this->firingDates($schedule, '2026-04-24', '2026-05-08'));
    }

    #[Test]
    public function a_patrol_routine_over_business_hours_with_a_lunch_break_rings_on_the_per_window_grid(): void
    {
        // The grid anchors at each window's start: 9:00, 11:00 / 13:00,
        // 15:00. No point during the lunch break (12:00–13:00), and 17:00
        // is outside the half-open interval.
        $evaluator = new YrnkEvaluator(
            calendar: new Calendar(
                businessHours: new BusinessHours([
                    TimeWindow::fromStrings('09:00', '12:00'),
                    TimeWindow::fromStrings('13:00', '17:00'),
                ]),
            ),
            timezone: new DateTimeZone('Asia/Tokyo'),
        );
        $schedule = (new ScheduleParser())->parse([
            'days' => ['weekday'],
            'times' => ['every' => [2, 'hour'], 'between' => 'business_hour'],
        ]);

        $this->assertTrue($evaluator->matches($schedule, new DateTimeImmutable('2026-07-13T09:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, new DateTimeImmutable('2026-07-13T11:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, new DateTimeImmutable('2026-07-13T13:00:00+09:00')));
        $this->assertTrue($evaluator->matches($schedule, new DateTimeImmutable('2026-07-13T15:00:00+09:00')));
        // No point during the lunch break (12:00–13:00), and 17:00 is
        // outside the half-open interval.
        $this->assertFalse($evaluator->matches($schedule, new DateTimeImmutable('2026-07-13T12:00:00+09:00')));
        $this->assertFalse($evaluator->matches($schedule, new DateTimeImmutable('2026-07-13T17:00:00+09:00')));
        // The next business day starts again at the window start.
        $this->assertTrue($evaluator->matches($schedule, new DateTimeImmutable('2026-07-14T09:00:00+09:00')));
    }

    // ---- helpers ----

    /**
     * @param  array<string, mixed>  $raw
     */
    private function schedule(array $raw): YrnkSchedule
    {
        return (new ScheduleParser())->parse($raw);
    }

    private function evaluator(): YrnkEvaluator
    {
        return new YrnkEvaluator(
            calendar: new Calendar(
                holidays: Holidays::ofDates(self::JAPANESE_HOLIDAYS_2026),
                businessHolidays: BusinessHolidays::ofDates([]),
                businessDays: BusinessDays::ofDates([]),
            ),
            timezone: new DateTimeZone('Asia/Tokyo'),
        );
    }

    /**
     * Enumerates the firing days (Y-m-d) by asking, for each day of the
     * period, "is there a matching date-time in that day's interval
     * [00:00, 24:00)".
     *
     * @return list<string>
     */
    private function firingDates(YrnkSchedule $schedule, string $firstDate, string $lastDate): array
    {
        $evaluator = $this->evaluator();
        $days = [];
        $end = new DateTimeImmutable("{$lastDate}T00:00:00+09:00");

        for ($day = new DateTimeImmutable("{$firstDate}T00:00:00+09:00"); $day <= $end; $day = $day->modify('+1 day')) {
            $days[] = $day;
        }

        $hits = array_filter(
            $days,
            fn(DateTimeImmutable $day): bool => $evaluator->hasMatchIn(
                $schedule,
                $day->modify('-1 second'),
                $day->modify('+1 day')->modify('-1 second'),
            ),
        );

        return array_values(array_map(
            static fn(DateTimeImmutable $day): string => $day->format('Y-m-d'),
            $hits,
        ));
    }
}
