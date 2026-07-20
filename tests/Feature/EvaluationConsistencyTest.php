<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Definitions\BusinessDays;
use Yarunoka\Definitions\BusinessHolidays;
use Yarunoka\Definitions\CustomDefinition;
use Yarunoka\Definitions\Definitions;
use Yarunoka\Definitions\Holidays;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\YrnkEvaluator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cross-checks the single check (matches) against the interval check
 * (hasMatchIn). For representative schedules, the list of matching days
 * from brute-forcing matches over every day's point of the period must
 * equal the list from hasMatchIn cut around each point. The single-day
 * decision and the per-month enumeration are separate implementations,
 * and this catches them drifting apart.
 *
 * The period is 2026-06-15 through 09-15 (three month boundaries,
 * including the Marine Day 7/20 and Mountain Day 8/11 holidays).
 */
class EvaluationConsistencyTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function representativeSchedules(): array
    {
        return [
            'every Monday and Thursday' => [['days' => ['mon', 'thu'], 'times' => ['10:00']], '10:00:00'],
            'payday on the 25th moved to the previous business day' => [
                ['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']], '10:00:00',
            ],
            'the end of the month moved to the next business day' => [
                ['days' => ['last_day_of_month'], 'shift' => ['next', 'or_same', 'business_day'], 'times' => ['10:00']],
                '10:00:00',
            ],
            'the strict prev of the 1st crosses the month boundary' => [
                ['days' => [1], 'shift' => ['prev', 'business_day'], 'times' => ['10:00']], '10:00:00',
            ],
            '1st and 3rd Friday skipping holidays' => [
                ['days' => [['1st', 'fri'], ['3rd', 'fri']], 'if' => ['not', 'holiday'], 'times' => ['10:00']],
                '10:00:00',
            ],
            'the fifth Monday' => [['days' => [['5th', 'mon']], 'times' => ['10:00']], '10:00:00'],
            'the business day before a break' => [
                ['days' => ['business_day'], 'if' => ['next', 'business_holiday'], 'times' => ['10:00']], '10:00:00',
            ],
            'the 27th of even months moved to the next business day' => [
                ['months' => [6, 8], 'days' => [27], 'shift' => ['next', 'or_same', 'business_day'], 'times' => ['10:00']],
                '10:00:00',
            ],
            'a one-off event' => [['years' => [2026], 'months' => [7], 'days' => [15], 'times' => ['10:00']], '10:00:00'],
            'a custom reference' => [['days' => ['anniversary'], 'times' => ['10:00']], '10:00:00'],
            'every 2 days' => [[
                'from' => '2026-06-20 00:00', 'days' => [['every', 2, 'day']], 'times' => ['10:00'],
            ], '10:00:00'],
            'every 3 days skipping holidays' => [[
                'from' => '2026-06-18 00:00', 'days' => [['every', 3, 'day']], 'if' => ['not', 'holiday'],
                'times' => ['10:00'],
            ], '10:00:00'],
            'every day bounded by from and until' => [[
                'from' => '2026-07-01 10:00', 'until' => '2026-08-15 10:00', 'times' => ['10:00'],
            ], '10:00:00'],
            'the third Monday as allday' => [['days' => [['3rd', 'mon']], 'allday' => true], '00:00:00'],
            'payday on the 25th as allday' => [
                ['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'allday' => true], '00:00:00',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    #[Test]
    #[DataProvider('representativeSchedules')]
    public function brute_forced_matches_and_interval_checks_agree_on_the_matching_days(array $raw, string $pointTime): void
    {
        $evaluator = $this->evaluator();
        $schedule = (new ScheduleParser())->parse($raw);
        $points = $this->pointsAt($pointTime);

        $byMatches = $this->dates(array_filter(
            $points,
            fn(DateTimeImmutable $point): bool => $evaluator->matches($schedule, $point),
        ));
        $byInterval = $this->dates(array_filter(
            $points,
            fn(DateTimeImmutable $point): bool => $evaluator->hasMatchIn(
                $schedule,
                $point->modify('-1 second'),
                $point,
            ),
        ));

        $this->assertSame($byMatches, $byInterval);
        $this->assertNotSame([], $byMatches, 'a representative with no matching day in the period cross-checks nothing');
    }

    #[Test]
    public function matches_and_interval_checks_agree_across_dst_transition_days(): void
    {
        // Two periods spanning the 2026 America/New_York transitions
        // (spring 3/8, fall 11/1). On 3/8 the wall 02:30 does not exist;
        // the forward push of the point (RFC 5545) must agree between the
        // two.
        $timezone = new DateTimeZone('America/New_York');
        $evaluator = new YrnkEvaluator(definitions: new Definitions(), timezone: $timezone);
        $schedule = (new ScheduleParser())->parse(['times' => ['02:30']]);
        $points = [
            ...$this->pointsBetween('02:30:00', '2026-03-01', '2026-03-14', $timezone),
            ...$this->pointsBetween('02:30:00', '2026-10-25', '2026-11-07', $timezone),
        ];

        $byMatches = $this->dates(array_filter(
            $points,
            fn(DateTimeImmutable $point): bool => $evaluator->matches($schedule, $point),
        ));
        $byInterval = $this->dates(array_filter(
            $points,
            fn(DateTimeImmutable $point): bool => $evaluator->hasMatchIn(
                $schedule,
                $point->modify('-1 second'),
                $point,
            ),
        ));

        $this->assertSame($byMatches, $byInterval);
        $this->assertNotSame([], $byMatches);
    }

    #[Test]
    public function a_schedule_that_never_matches_is_empty_by_both_questions(): void
    {
        $evaluator = $this->evaluator();
        $schedule = (new ScheduleParser())->parse(['years' => [2020], 'months' => [7], 'days' => [15], 'times' => ['10:00']]);
        $points = $this->pointsAt('10:00:00');

        $byMatches = $this->dates(array_filter(
            $points,
            fn(DateTimeImmutable $point): bool => $evaluator->matches($schedule, $point),
        ));
        $byInterval = $this->dates(array_filter(
            $points,
            fn(DateTimeImmutable $point): bool => $evaluator->hasMatchIn(
                $schedule,
                $point->modify('-1 second'),
                $point,
            ),
        ));

        $this->assertSame([], $byMatches);
        $this->assertSame([], $byInterval);
    }

    // ---- helpers ----

    private function evaluator(): YrnkEvaluator
    {
        return new YrnkEvaluator(
            definitions: new Definitions(
                holidays: Holidays::ofDates(['2026-07-20', '2026-08-11']),
                businessHolidays: BusinessHolidays::ofDates([]),
                businessDays: BusinessDays::ofDates([]),
                custom: ['anniversary' => CustomDefinition::ofDates(['2026-07-05', '2026-08-20'])],
            ),
            timezone: new DateTimeZone('Asia/Tokyo'),
        );
    }

    /**
     * The point at the given time of each day of the period (2026-06-15
     * through 09-15).
     *
     * @return list<DateTimeImmutable>
     */
    private function pointsAt(string $time): array
    {
        return $this->pointsBetween($time, '2026-06-15', '2026-09-15', new DateTimeZone('Asia/Tokyo'));
    }

    /**
     * The point at the given wall-clock time of each day of the period.
     * Nonexistent wall times follow PHP's resolution (= the RFC 5545
     * forward push).
     *
     * @return list<DateTimeImmutable>
     */
    private function pointsBetween(string $time, string $firstDate, string $lastDate, DateTimeZone $timezone): array
    {
        $points = [];
        $end = new DateTimeImmutable("{$lastDate}T{$time}", $timezone);

        for ($point = new DateTimeImmutable("{$firstDate}T{$time}", $timezone); $point <= $end; $point = $point->modify('+1 day')) {
            $points[] = $point;
        }

        return $points;
    }

    /**
     * @param  array<int, DateTimeImmutable>  $points
     * @return list<string>
     */
    private function dates(array $points): array
    {
        return array_values(array_map(
            static fn(DateTimeImmutable $point): string => $point->format('Y-m-d'),
            $points,
        ));
    }
}
