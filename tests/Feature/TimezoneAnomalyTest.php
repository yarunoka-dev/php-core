<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Schedule\YrnkScheduleParser;
use Yarunoka\YrnkDate;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A zone does more to midnight than move it by an hour, and the start of
 * a day is where an all-day occurrence stands. Every shape a transition
 * can give that start is pinned here, and the three queries are checked
 * against one another on each: they read the calendar from opposite ends
 * — the enumeration chooses a day and resolves it, the point check reads
 * a day off an instant — so a shape that only one of them handles shows
 * up as a disagreement.
 *
 * The shapes, counted over the whole tz database from 1900 to 2036:
 *
 *     midnight moves within its day     1570 transitions, still occurring
 *     the whole day disappears             5 transitions, all historical
 *     midnight happens twice             347 transitions, still occurring
 *
 * The five days that disappear are date line crossings: Pacific/Apia and
 * Pacific/Fakaofo (2011-12-30), Pacific/Kanton and Pacific/Kiritimati
 * (1994-12-31), and Pacific/Kwajalein (1993-08-21).
 */
class TimezoneAnomalyTest extends TestCase
{
    /**
     * A calendar day per shape, with the day its start resolves to.
     *
     * @return array<string, array{string, int, int, int, string}>
     */
    public static function shapes(): array
    {
        return [
            'an ordinary midnight' => ['Asia/Tokyo', 2026, 7, 14, '2026-07-14'],
            'midnight moves within its day' => ['Africa/Cairo', 2026, 4, 24, '2026-04-24'],
            'the whole day disappears' => ['Pacific/Apia', 2011, 12, 30, '2011-12-31'],
            'midnight happens twice' => ['America/Havana', 2026, 11, 1, '2026-11-01'],
        ];
    }

    #[Test]
    #[DataProvider('shapes')]
    public function an_allday_occurrence_is_answered_on_the_day_its_start_resolves_to(
        string $zone,
        int $year,
        int $month,
        int $day,
        string $expected,
    ): void {
        $timezone = new DateTimeZone($zone);
        $occurrences = $this->enumerate($this->allday($year, $month, $day, $timezone), $timezone, $year, $month);

        $this->assertCount(1, $occurrences);
        $this->assertInstanceOf(YrnkDate::class, $occurrences[0]);
        $this->assertSame($expected, $occurrences[0]->format('Y-m-d'));
    }

    #[Test]
    #[DataProvider('shapes')]
    public function the_point_check_says_yes_for_every_instant_of_an_allday_occurrences_day(
        string $zone,
        int $year,
        int $month,
        int $day,
        string $expected,
    ): void {
        $timezone = new DateTimeZone($zone);
        $schedule = $this->allday($year, $month, $day, $timezone);
        $evaluator = $this->evaluator($timezone);

        foreach ($this->instantsAcross($expected, $timezone) as $label => $instant) {
            $this->assertTrue(
                $evaluator->matches($schedule, $instant),
                "the all-day occurrence of {$expected} should match at its {$label}",
            );
        }
    }

    #[Test]
    #[DataProvider('shapes')]
    public function the_interval_check_agrees_with_the_enumeration_for_an_allday_occurrence(
        string $zone,
        int $year,
        int $month,
        int $day,
        string $expected,
    ): void {
        $timezone = new DateTimeZone($zone);
        $schedule = $this->allday($year, $month, $day, $timezone);

        // The window opens a month early so that the occurrence is never
        // the excluded left end of the half-open interval.
        $this->assertTrue($this->coversTheMonth($schedule, $timezone, $year, $month));
    }

    #[Test]
    #[DataProvider('shapes')]
    public function a_timed_occurrence_is_answered_at_the_point_its_wall_time_resolves_to(
        string $zone,
        int $year,
        int $month,
        int $day,
        string $expected,
    ): void {
        $timezone = new DateTimeZone($zone);
        $schedule = $this->timedAtMidnight($year, $month, $day, $timezone);
        $evaluator = $this->evaluator($timezone);
        $occurrences = $this->enumerate($schedule, $timezone, $year, $month);

        $this->assertCount(1, $occurrences);
        $this->assertSame($expected, $occurrences[0]->format('Y-m-d'));

        // The three queries have to name the same point.
        $this->assertTrue($evaluator->matches($schedule, DateTimeImmutable::createFromInterface($occurrences[0])));
        $this->assertTrue($this->coversTheMonth($schedule, $timezone, $year, $month));
    }

    private function allday(int $year, int $month, int $day, DateTimeZone $timezone): YrnkSchedule
    {
        return (new YrnkScheduleParser())->parse(
            ['years' => [$year], 'months' => [$month], 'days' => [$day], 'allday' => true],
            $timezone,
        );
    }

    private function timedAtMidnight(int $year, int $month, int $day, DateTimeZone $timezone): YrnkSchedule
    {
        return (new YrnkScheduleParser())->parse(
            ['years' => [$year], 'months' => [$month], 'days' => [$day], 'times' => ['00:00']],
            $timezone,
        );
    }

    private function evaluator(DateTimeZone $timezone): YrnkEvaluator
    {
        return new YrnkEvaluator(calendar: new YrnkCalendar(), timezone: $timezone);
    }

    /**
     * @return list<YrnkDate|DateTimeImmutable>
     */
    private function enumerate(YrnkSchedule $schedule, DateTimeZone $timezone, int $year, int $month): array
    {
        $from = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00', $year, $month), $timezone);

        return $this->evaluator($timezone)->occurrencesIn($schedule, $from, $from->modify('+2 months'));
    }

    private function coversTheMonth(YrnkSchedule $schedule, DateTimeZone $timezone, int $year, int $month): bool
    {
        $from = (new DateTimeImmutable(sprintf('%04d-%02d-01 00:00', $year, $month), $timezone))->modify('-1 month');

        return $this->evaluator($timezone)->hasMatchIn($schedule, $from, $from->modify('+3 months'));
    }

    /**
     * The start, the middle, and the last second of a local date.
     *
     * @return array<string, DateTimeImmutable>
     */
    private function instantsAcross(string $date, DateTimeZone $timezone): array
    {
        $start = new DateTimeImmutable($date, $timezone);

        return [
            'start' => $start,
            'midday' => new DateTimeImmutable("{$date} 12:00", $timezone),
            'last second' => new DateTimeImmutable("{$date} 23:59:59", $timezone),
        ];
    }
}
