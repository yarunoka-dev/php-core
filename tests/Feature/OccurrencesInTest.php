<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Calendar\BusinessDays;
use Yarunoka\Calendar\BusinessHolidays;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Calendar\Holidays;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\Time\LocalDate;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The enumeration query (occurrencesIn): the occurrence set cut to the
 * closed window [from, to], timed occurrences answered as instants and
 * all-day occurrences as dates, in ascending order of comparison
 * instant. Results are rendered to strings so that both the values and
 * the returned kinds (instant vs date) are asserted at once.
 */
class OccurrencesInTest extends TestCase
{
    // ---- the window and its boundaries ----

    #[Test]
    public function fixed_times_are_listed_ascending_with_both_window_ends_included(): void
    {
        $schedule = $this->schedule(['times' => ['09:00', '12:00']]);

        $this->assertSame(
            ['2026-07-12T09:00:00+09:00', '2026-07-12T12:00:00+09:00', '2026-07-13T09:00:00+09:00'],
            $this->rendered($this->evaluator()->occurrencesIn(
                $schedule,
                $this->at('2026-07-12T09:00:00+09:00'),
                $this->at('2026-07-13T09:00:00+09:00'),
            )),
        );
    }

    #[Test]
    public function a_single_instant_window_answers_the_point_at_it(): void
    {
        $schedule = $this->schedule(['times' => ['09:00']]);
        $point = $this->at('2026-07-12T09:00:00+09:00');

        $this->assertSame(
            ['2026-07-12T09:00:00+09:00'],
            $this->rendered($this->evaluator()->occurrencesIn($schedule, $point, $point)),
        );
    }

    #[Test]
    public function an_inverted_window_is_the_empty_interval(): void
    {
        $schedule = $this->schedule(['times' => ['09:00']]);

        $this->assertSame([], $this->evaluator()->occurrencesIn(
            $schedule,
            $this->at('2026-07-13T00:00:00+09:00'),
            $this->at('2026-07-12T00:00:00+09:00'),
        ));
    }

    #[Test]
    public function the_window_ends_are_compared_as_raw_instants_without_truncation(): void
    {
        $schedule = $this->schedule(['times' => ['09:00']]);
        $to = $this->at('2026-07-12T10:00:00+09:00');

        $this->assertSame([], $this->evaluator()->occurrencesIn(
            $schedule,
            $this->at('2026-07-12T09:00:00.500000+09:00'),
            $to,
        ));
        $this->assertSame(
            ['2026-07-12T09:00:00+09:00'],
            $this->rendered($this->evaluator()->occurrencesIn(
                $schedule,
                $this->at('2026-07-12T08:59:59.500000+09:00'),
                $to,
            )),
        );
    }

    // ---- the two kinds of occurrences ----

    #[Test]
    public function allday_occurrences_are_answered_as_dates(): void
    {
        $schedule = $this->schedule(['days' => ['mon'], 'allday' => true]);

        $occurrences = $this->evaluator()->occurrencesIn(
            $schedule,
            $this->at('2026-07-12T00:00:00+09:00'),
            $this->at('2026-07-27T00:00:00+09:00'),
        );

        $this->assertContainsOnlyInstancesOf(LocalDate::class, $occurrences);
        // The window end is included: the comparison instant of 7/27 is
        // exactly at to.
        $this->assertSame(['2026-07-13', '2026-07-20', '2026-07-27'], $this->rendered($occurrences));
    }

    #[Test]
    public function an_allday_occurrence_whose_comparison_instant_precedes_the_window_is_out_of_range(): void
    {
        $schedule = $this->schedule(['days' => ['mon'], 'allday' => true]);

        // 7/13 is a Monday, but its comparison instant (7/13 00:00) lies
        // before the window start at noon.
        $this->assertSame(['2026-07-20'], $this->rendered($this->evaluator()->occurrencesIn(
            $schedule,
            $this->at('2026-07-13T12:00:00+09:00'),
            $this->at('2026-07-20T00:00:00+09:00'),
        )));
    }

    #[Test]
    public function timed_occurrences_are_answered_in_the_configured_timezone(): void
    {
        $schedule = $this->schedule(['times' => ['09:00']]);

        // The window may be named in any timezone; the answer stays on
        // the document's clock. Both ends land exactly on points (09:00
        // JST = 00:00 UTC), so the closed interval keeps both.
        $occurrences = $this->evaluator()->occurrencesIn(
            $schedule,
            new DateTimeImmutable('2026-07-12 00:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-07-13 00:00:00', new DateTimeZone('UTC')),
        );

        $this->assertSame(['2026-07-12T09:00:00+09:00', '2026-07-13T09:00:00+09:00'], $this->rendered($occurrences));
    }

    // ---- the validity range ----

    #[Test]
    public function the_validity_range_clips_inside_the_window(): void
    {
        $schedule = $this->schedule([
            'from' => '2026-07-10 00:00', 'until' => '2026-07-15 00:00', 'times' => ['00:00'],
        ]);

        // from is included, until is not (the half-open [from, until)):
        // the 7/15 00:00 point does not exist.
        $this->assertSame(
            [
                '2026-07-10T00:00:00+09:00',
                '2026-07-11T00:00:00+09:00',
                '2026-07-12T00:00:00+09:00',
                '2026-07-13T00:00:00+09:00',
                '2026-07-14T00:00:00+09:00',
            ],
            $this->rendered($this->evaluator()->occurrencesIn(
                $schedule,
                $this->at('2026-07-01T00:00:00+09:00'),
                $this->at('2026-07-31T00:00:00+09:00'),
            )),
        );
    }

    // ---- the clock grid ----

    #[Test]
    public function the_clock_grid_lays_its_points_inside_each_window(): void
    {
        $schedule = $this->schedule([
            'days' => ['mon'],
            'times' => ['every' => [6, 'hour'], 'between' => ['08:00', '20:00']],
        ]);

        $this->assertSame(
            ['2026-07-13T08:00:00+09:00', '2026-07-13T14:00:00+09:00'],
            $this->rendered($this->evaluator()->occurrencesIn(
                $schedule,
                $this->at('2026-07-13T00:00:00+09:00'),
                $this->at('2026-07-13T23:59:59+09:00'),
            )),
        );
    }

    // ---- shift across the window's months ----

    #[Test]
    public function a_base_day_in_the_month_before_the_window_shifts_into_it(): void
    {
        // 2026-07-31 is a Friday; the strict next business day is Monday
        // 8/3. The base day lies in a month the window does not touch.
        $schedule = $this->schedule([
            'days' => ['last_day_of_month'], 'shift' => ['next', 'business_day'], 'times' => ['09:00'],
        ]);

        $this->assertSame(['2026-08-03T09:00:00+09:00'], $this->rendered($this->evaluator()->occurrencesIn(
            $schedule,
            $this->at('2026-08-01T00:00:00+09:00'),
            $this->at('2026-08-05T00:00:00+09:00'),
        )));
    }

    #[Test]
    public function a_base_day_in_the_month_after_the_window_shifts_back_into_it(): void
    {
        // 2026-08-01 is a Saturday; prev-or-same lands on Friday 7/31.
        $schedule = $this->schedule([
            'days' => [1], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00'],
        ]);

        $this->assertSame(['2026-07-31T10:00:00+09:00'], $this->rendered($this->evaluator()->occurrencesIn(
            $schedule,
            $this->at('2026-07-25T00:00:00+09:00'),
            $this->at('2026-07-31T23:59:00+09:00'),
        )));
    }

    #[Test]
    public function weekend_days_landing_on_the_same_friday_collapse_to_one_occurrence(): void
    {
        // 7/11 (Sat) and 7/12 (Sun) both land on Friday 7/10.
        $schedule = $this->schedule([
            'days' => ['sat', 'sun'], 'shift' => ['prev', 'business_day'], 'times' => ['09:00'],
        ]);

        $this->assertSame(['2026-07-10T09:00:00+09:00'], $this->rendered($this->evaluator()->occurrencesIn(
            $schedule,
            $this->at('2026-07-10T00:00:00+09:00'),
            $this->at('2026-07-12T23:59:00+09:00'),
        )));
    }

    // ---- the day cycle ----

    #[Test]
    public function the_day_cycle_counts_from_its_anchor(): void
    {
        $schedule = $this->schedule([
            'from' => '2026-07-14 00:00', 'days' => [['every', 2, 'day']], 'times' => ['03:00'],
        ]);

        $this->assertSame(
            ['2026-07-14T03:00:00+09:00', '2026-07-16T03:00:00+09:00', '2026-07-18T03:00:00+09:00'],
            $this->rendered($this->evaluator()->occurrencesIn(
                $schedule,
                $this->at('2026-07-14T00:00:00+09:00'),
                $this->at('2026-07-20T00:00:00+09:00'),
            )),
        );
    }

    // ---- the interval sequence (every directly on a schedule) ----

    #[Test]
    public function sequence_points_inside_the_window_with_both_ends_included(): void
    {
        $schedule = $this->schedule(['from' => '2026-07-17 10:00', 'every' => [7, 'hour']]);

        $this->assertSame(
            ['2026-07-17T10:00:00+09:00', '2026-07-17T17:00:00+09:00', '2026-07-18T00:00:00+09:00'],
            $this->rendered($this->evaluator()->occurrencesIn(
                $schedule,
                $this->at('2026-07-17T10:00:00+09:00'),
                $this->at('2026-07-18T00:00:00+09:00'),
            )),
        );
    }

    #[Test]
    public function a_window_far_from_the_anchor_is_answered_without_walking_from_it(): void
    {
        // 36 hours = 1.5 days: half a year later the row stands at 00:00
        // and 12:00 on alternating days.
        $schedule = $this->schedule(['from' => '2026-01-01 00:00', 'every' => [36, 'hour']]);

        $this->assertSame(
            ['2026-07-12T00:00:00+09:00', '2026-07-13T12:00:00+09:00', '2026-07-15T00:00:00+09:00'],
            $this->rendered($this->evaluator()->occurrencesIn(
                $schedule,
                $this->at('2026-07-12T00:00:00+09:00'),
                $this->at('2026-07-15T00:00:00+09:00'),
            )),
        );
    }

    #[Test]
    public function the_validity_until_clips_a_sequence(): void
    {
        $schedule = $this->schedule([
            'from' => '2026-07-17 10:00', 'until' => '2026-07-18 00:00', 'every' => [7, 'hour'],
        ]);

        // The 7/18 00:00 point is cut by the half-open [from, until).
        $this->assertSame(
            ['2026-07-17T10:00:00+09:00', '2026-07-17T17:00:00+09:00'],
            $this->rendered($this->evaluator()->occurrencesIn(
                $schedule,
                $this->at('2026-07-17T00:00:00+09:00'),
                $this->at('2026-07-19T00:00:00+09:00'),
            )),
        );
    }

    #[Test]
    public function sequence_points_around_a_dst_gap_are_answered_in_instant_order(): void
    {
        // The 45-minute row crosses the 02:00 → 03:00 gap: wall 02:15
        // does not exist and is pushed to 03:15 EDT, standing after the
        // wall 03:00 point in real time. The answer follows the
        // instants, not the wall order.
        $evaluator = new YrnkEvaluator(new Calendar(), new DateTimeZone('America/New_York'));
        $schedule = $this->schedule(['from' => '2026-03-08 00:00', 'every' => [45, 'minute']]);

        $this->assertSame(
            [
                '2026-03-08T00:00:00-05:00',
                '2026-03-08T00:45:00-05:00',
                '2026-03-08T01:30:00-05:00',
                '2026-03-08T03:00:00-04:00',
                '2026-03-08T03:15:00-04:00',
            ],
            $this->rendered($evaluator->occurrencesIn(
                $schedule,
                $this->at('2026-03-08T00:00:00-05:00'),
                $this->at('2026-03-08T03:30:00-04:00'),
            )),
        );
    }

    #[Test]
    public function wall_times_folded_by_the_gap_collapse_in_a_sequence_too(): void
    {
        // Wall 02:00 is pushed onto the wall 03:00 point's instant; the
        // set contains that point once.
        $evaluator = new YrnkEvaluator(new Calendar(), new DateTimeZone('America/New_York'));
        $schedule = $this->schedule(['from' => '2026-03-08 01:00', 'every' => [60, 'minute']]);

        $this->assertSame(
            ['2026-03-08T01:00:00-05:00', '2026-03-08T03:00:00-04:00', '2026-03-08T04:00:00-04:00'],
            $this->rendered($evaluator->occurrencesIn(
                $schedule,
                $this->at('2026-03-08T01:00:00-05:00'),
                $this->at('2026-03-08T04:00:00-04:00'),
            )),
        );
    }

    // ---- DST transitions (RFC 5545 §3.3.5) ----

    #[Test]
    public function wall_times_folded_together_by_the_gap_collapse_to_one_instant(): void
    {
        // America/New_York transitions 02:00 EST → 03:00 EDT on
        // 2026-03-08: the 02:30 point is pushed onto the 03:30 one.
        $evaluator = new YrnkEvaluator(new Calendar(), new DateTimeZone('America/New_York'));
        $schedule = $this->schedule(['days' => [8], 'times' => ['02:30', '03:30']]);

        $this->assertSame(['2026-03-08T03:30:00-04:00'], $this->rendered($evaluator->occurrencesIn(
            $schedule,
            $this->at('2026-03-08T00:00:00-05:00'),
            $this->at('2026-03-08T04:00:00-04:00'),
        )));
    }

    // ---- agreement with the other queries ----

    #[Test]
    public function every_enumerated_instant_matches_and_no_point_hides_between_neighbours(): void
    {
        $schedule = $this->schedule([
            'days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00'],
        ]);
        $evaluator = $this->evaluator();

        $occurrences = $evaluator->occurrencesIn(
            $schedule,
            $this->at('2026-07-01T00:00:00+09:00'),
            $this->at('2026-10-31T23:59:00+09:00'),
        );

        // 7/25 Sat → 7/24; 8/25 Tue; 9/25 Fri; 10/25 Sun → 10/23.
        $this->assertSame(
            [
                '2026-07-24T10:00:00+09:00',
                '2026-08-25T10:00:00+09:00',
                '2026-09-25T10:00:00+09:00',
                '2026-10-23T10:00:00+09:00',
            ],
            $this->rendered($occurrences),
        );

        foreach ($occurrences as $occurrence) {
            $this->assertInstanceOf(DateTimeImmutable::class, $occurrence);
            $this->assertTrue($evaluator->matches($schedule, $occurrence));
        }

        foreach (array_slice($occurrences, 1) as $index => $next) {
            $previous = $occurrences[$index];
            $this->assertInstanceOf(DateTimeImmutable::class, $previous);
            $this->assertInstanceOf(DateTimeImmutable::class, $next);
            $this->assertFalse($evaluator->hasMatchIn($schedule, $previous, $next->modify('-1 second')));
        }
    }

    // ---- helpers ----

    private function evaluator(): YrnkEvaluator
    {
        return new YrnkEvaluator(
            calendar: new Calendar(
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
        return (new ScheduleParser())->parse($raw);
    }

    private function at(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso);
    }

    /**
     * Renders occurrences kind-faithfully: an instant as ISO 8601 with
     * its offset, a date as YYYY-MM-DD.
     *
     * @param  list<DateTimeImmutable|LocalDate>  $occurrences
     * @return list<string>
     */
    private function rendered(array $occurrences): array
    {
        return array_map(
            static fn(DateTimeImmutable|LocalDate $occurrence): string => $occurrence instanceof LocalDate
                ? $occurrence->toString()
                : $occurrence->format('Y-m-d\TH:i:sP'),
            $occurrences,
        );
    }
}
