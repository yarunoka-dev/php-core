<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkParser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * An all-day occurrence carries no time, so a range holds it as soon as
 * it holds any part of its day. The three questions read it the same way.
 */
class AllDayDayMembershipTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $schedule
     * @return array{YrnkEvaluator, \Yarunoka\YrnkSchedule}
     */
    private function evaluatorFor(array $schedule, string $timezone = 'Asia/Tokyo'): array
    {
        $document = (new YrnkParser())->parse([
            'version' => '1.0',
            'timezone' => $timezone,
            'schedules' => [$schedule],
        ]);

        return [
            new YrnkEvaluator($document->calendar, $document->timezone),
            $document->schedules[0],
        ];
    }

    private function at(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso);
    }

    /**
     * @param  list<\Yarunoka\YrnkDate|\Yarunoka\YrnkDateTime>  $occurrences
     * @return list<string>
     */
    private function rendered(array $occurrences): array
    {
        return array_map(static fn($o): string => $o->format('Y-m-d'), $occurrences);
    }

    #[Test]
    public function what_is_on_today_answers_the_same_at_any_hour_of_that_day(): void
    {
        [$evaluator, $schedule] = $this->evaluatorFor(['days' => [29], 'allday' => true]);
        $endOfDay = $this->at('2026-07-30T00:00:00+09:00');

        foreach (['00:00:00', '01:53:00', '12:00:00', '23:59:59'] as $now) {
            $this->assertSame(
                ['2026-07-29'],
                $this->rendered($evaluator->occurrencesIn($schedule, $this->at("2026-07-29T{$now}+09:00"), $endOfDay)),
                "asked at {$now}",
            );
        }
    }

    #[Test]
    public function the_three_questions_agree_partway_through_the_day(): void
    {
        [$evaluator, $schedule] = $this->evaluatorFor(['days' => [29], 'allday' => true]);
        $now = $this->at('2026-07-29T01:53:00+09:00');

        $this->assertTrue($evaluator->matches($schedule, $now));
        $this->assertTrue($evaluator->hasMatchIn($schedule, $this->at('2026-07-29T01:52:00+09:00'), $now));
        $this->assertSame(
            ['2026-07-29'],
            $this->rendered($evaluator->occurrencesIn($schedule, $now, $this->at('2026-07-30T00:00:00+09:00'))),
        );
    }

    #[Test]
    public function a_window_that_ends_at_the_start_of_the_day_does_not_reach_it(): void
    {
        [$evaluator, $schedule] = $this->evaluatorFor(['days' => [29], 'allday' => true]);

        $this->assertSame([], $this->rendered($evaluator->occurrencesIn(
            $schedule,
            $this->at('2026-07-28T00:00:00+09:00'),
            $this->at('2026-07-28T23:59:59+09:00'),
        )));
    }

    #[Test]
    public function an_until_at_the_start_of_a_day_ends_the_day_before(): void
    {
        [$evaluator, $schedule] = $this->evaluatorFor([
            'days' => [28, 29],
            'allday' => true,
            'until' => '2026-07-29 00:00',
        ]);

        $this->assertSame(['2026-07-28'], $this->rendered($evaluator->occurrencesIn(
            $schedule,
            $this->at('2026-07-01T00:00:00+09:00'),
            $this->at('2026-07-31T00:00:00+09:00'),
        )));
    }

    #[Test]
    public function a_day_whose_midnight_the_zone_skips_is_held_like_any_other(): void
    {
        // America/Santiago has no 00:00 on 2025-09-07: the clock jumps to
        // 01:00, which used to collide with a boundary written there.
        [$evaluator, $schedule] = $this->evaluatorFor(
            ['days' => [7], 'allday' => true, 'from' => '2025-09-07 01:00'],
            'America/Santiago',
        );

        $this->assertSame(['2025-09-07'], $this->rendered($evaluator->occurrencesIn(
            $schedule,
            $this->at('2025-09-07T12:00:00-03:00'),
            $this->at('2025-09-30T00:00:00-03:00'),
        )));
    }
}
