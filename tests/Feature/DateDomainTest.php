<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Schedule\YrnkScheduleParser;
use Yarunoka\YrnkDate;
use Yarunoka\YrnkDateTime;
use Yarunoka\YrnkEvaluator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The date domain of the evaluation model: evaluation works over
 * 0001-01-01 through 9999-12-31, read as calendar days on the document
 * timezone's clock. At the edges evaluation ends rather than fails — a
 * recurrence generates only its intersection with the domain, a shift
 * search that would leave it finds no landing, an if whose neighbour
 * lies outside fails the whole guard, and a query is answered on its
 * overlap with the domain.
 */
class DateDomainTest extends TestCase
{
    // ---- shift at the edges ----

    #[Test]
    public function a_shift_searching_past_the_upper_edge_finds_no_landing(): void
    {
        // 9999-12-31 is a Friday; the next Monday lies past the domain.
        // The base day produces no occurrences and the document stays
        // valid, exactly as when the 366-day cap runs out.
        $schedule = $this->schedule(
            ['years' => [9999], 'months' => [12], 'days' => [31], 'shift' => ['next', 'mon'], 'times' => ['09:00']],
        );

        $this->assertFalse($this->evaluator()->hasMatchIn(
            $schedule,
            new DateTimeImmutable('9999-12-01T00:00:00Z'),
            new DateTimeImmutable('9999-12-31T23:59:59Z'),
        ));
    }

    #[Test]
    public function a_shift_searching_past_the_lower_edge_finds_no_landing(): void
    {
        // The day before 0001-01-01 would be a Sunday, but the search
        // stops at the domain's lower edge.
        $schedule = $this->schedule(
            ['years' => [1], 'months' => [1], 'days' => [1], 'shift' => ['prev', 'sun'], 'times' => ['09:00']],
        );

        $this->assertFalse($this->evaluator()->hasMatchIn(
            $schedule,
            new DateTimeImmutable('0001-01-01T00:00:00Z'),
            new DateTimeImmutable('0001-01-31T23:59:59Z'),
        ));
    }

    #[Test]
    public function a_matches_question_on_a_shifted_schedule_survives_the_upper_edge(): void
    {
        // The reverse walk (is there a base day landing here?) reaches
        // the edge without leaving the domain.
        $schedule = $this->schedule(
            ['years' => [9999], 'months' => [12], 'days' => [31], 'shift' => ['next', 'or_same', 'fri'], 'times' => ['09:00']],
        );

        $this->assertTrue($this->evaluator()->matches($schedule, new DateTimeImmutable('9999-12-31T09:00:00Z')));
    }

    // ---- if at the edges ----

    #[Test]
    public function an_if_whose_next_neighbour_lies_outside_the_domain_fails_the_guard(): void
    {
        // The day after 9999-12-31 would be a Saturday, but there is no
        // such day to test.
        $schedule = $this->schedule(
            ['years' => [9999], 'months' => [12], 'days' => [31], 'if' => ['next', 'sat'], 'times' => ['09:00']],
        );

        $this->assertFalse($this->evaluator()->matches($schedule, new DateTimeImmutable('9999-12-31T09:00:00Z')));
    }

    #[Test]
    public function the_neighbour_guard_fails_before_not_applies(): void
    {
        // "No such day" is not a falsehood for not to turn into a match.
        $schedule = $this->schedule(
            ['years' => [9999], 'months' => [12], 'days' => [31], 'if' => ['next', 'not', 'mon'], 'times' => ['09:00']],
        );

        $this->assertFalse($this->evaluator()->matches($schedule, new DateTimeImmutable('9999-12-31T09:00:00Z')));
    }

    #[Test]
    public function an_if_whose_prev_neighbour_lies_outside_the_domain_fails_the_guard(): void
    {
        $schedule = $this->schedule(
            ['years' => [1], 'months' => [1], 'days' => [1], 'if' => ['prev', 'sun'], 'times' => ['09:00']],
        );

        $this->assertFalse($this->evaluator()->matches($schedule, new DateTimeImmutable('0001-01-01T09:00:00Z')));
    }

    // ---- the edge days themselves stay answerable ----

    #[Test]
    public function an_allday_enumeration_answers_the_domains_last_day(): void
    {
        $schedule = $this->schedule(['allday' => true]);

        $answer = $this->evaluator()->occurrencesIn(
            $schedule,
            new DateTimeImmutable('9999-12-31T00:00:00Z'),
            new DateTimeImmutable('9999-12-31T23:59:59Z'),
        );

        $this->assertCount(1, $answer);
        $this->assertInstanceOf(YrnkDate::class, $answer[0]);
        $this->assertSame('9999-12-31', $answer[0]->format('Y-m-d'));
    }

    #[Test]
    public function a_non_matching_question_on_the_domains_first_day_answers_false(): void
    {
        // The vanished-day fallback looks at the previous day, which the
        // first day of the domain does not have.
        $schedule = $this->schedule(['days' => [2], 'times' => ['09:00']]);

        $this->assertFalse($this->evaluator()->matches($schedule, new DateTimeImmutable('0001-01-01T09:00:00Z')));
    }

    // ---- the query cut ----

    #[Test]
    public function a_query_endpoint_beyond_the_edge_is_cut_to_the_bound(): void
    {
        // In a UTC+14 zone the domain ends at the instant of 10000-01-01
        // 00:00 local (9999-12-31T10:00:00Z); the overlap covers the last
        // hours of 9999-12-31 local and nothing beyond.
        $schedule = $this->schedule(['allday' => true]);
        $answer = $this->evaluator('Etc/GMT-14')->occurrencesIn(
            $schedule,
            new DateTimeImmutable('9999-12-31T09:00:00Z'),
            new DateTimeImmutable('9999-12-31T12:00:00Z'),
        );

        $this->assertCount(1, $answer);
        $this->assertSame('9999-12-31', $answer[0]->format('Y-m-d'));
    }

    #[Test]
    public function a_query_lying_entirely_past_the_domain_answers_empty(): void
    {
        $schedule = $this->schedule(['allday' => true]);

        $this->assertSame([], $this->evaluator('Etc/GMT-14')->occurrencesIn(
            $schedule,
            new DateTimeImmutable('9999-12-31T10:30:00Z'),
            new DateTimeImmutable('9999-12-31T12:00:00Z'),
        ));
    }

    #[Test]
    public function a_query_lying_entirely_before_the_domain_answers_empty(): void
    {
        // In a UTC-12 zone the domain starts at 0001-01-01T12:00:00Z, and
        // both endpoints lie before it.
        $schedule = $this->schedule(['allday' => true]);

        $this->assertSame([], $this->evaluator('Etc/GMT+12')->occurrencesIn(
            $schedule,
            new DateTimeImmutable('0001-01-01T00:00:00Z'),
            new DateTimeImmutable('0001-01-01T06:00:00Z'),
        ));
    }

    #[Test]
    public function a_period_beyond_the_domain_answers_false(): void
    {
        $schedule = $this->schedule(['allday' => true]);

        $this->assertFalse($this->evaluator('Etc/GMT-14')->hasMatchIn(
            $schedule,
            new DateTimeImmutable('9999-12-31T10:30:00Z'),
            new DateTimeImmutable('9999-12-31T12:00:00Z'),
        ));
    }

    #[Test]
    public function a_point_outside_the_domain_answers_false(): void
    {
        $schedule = $this->schedule(['allday' => true]);

        $this->assertFalse($this->evaluator()->matches($schedule, new DateTimeImmutable('0000-12-31T23:00:00Z')));
    }

    // ---- recurrences end at the domain ----

    #[Test]
    public function a_sequence_generates_only_its_intersection_with_the_domain(): void
    {
        // A minute sequence anchored at 9999-12-31 23:59 local generates
        // that one point and ends: the next point would fall on a day
        // past 9999-12-31 (the query endpoint beyond the domain is cut).
        $schedule = $this->schedule(
            ['from' => '9999-12-31 23:59', 'every' => [1, 'minute']],
            'Etc/GMT-14',
        );

        $answer = $this->evaluator('Etc/GMT-14')->occurrencesIn(
            $schedule,
            new DateTimeImmutable('9999-12-31T09:50:00Z'),
            new DateTimeImmutable('9999-12-31T12:00:00Z'),
        );

        $this->assertCount(1, $answer);
        $this->assertInstanceOf(YrnkDateTime::class, $answer[0]);
        $this->assertSame('9999-12-31 23:59', $answer[0]->format('Y-m-d H:i'));
    }

    #[Test]
    public function an_occurrence_may_exceed_the_domain_by_the_zone_offset(): void
    {
        // The domain closes on calendar days read on the document clock,
        // not on instants: in a UTC+14 zone the first day's instants
        // begin before 0001-01-01T00:00:00Z, and the timed occurrence at
        // 00:30 local is generated and answered.
        $schedule = $this->schedule(
            ['years' => [1], 'months' => [1], 'days' => [1], 'times' => ['00:30']],
            'Etc/GMT-14',
        );

        $answer = $this->evaluator('Etc/GMT-14')->occurrencesIn(
            $schedule,
            new DateTimeImmutable('0000-12-31T10:00:00Z'),
            new DateTimeImmutable('0000-12-31T11:00:00Z'),
        );

        $this->assertCount(1, $answer);
        $this->assertSame('0001-01-01 00:30', $answer[0]->format('Y-m-d H:i'));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function schedule(array $raw, string $timezone = 'UTC'): \Yarunoka\YrnkSchedule
    {
        return (new YrnkScheduleParser())->parse($raw, new DateTimeZone($timezone));
    }

    private function evaluator(string $timezone = 'UTC'): YrnkEvaluator
    {
        return new YrnkEvaluator(new YrnkCalendar(), new DateTimeZone($timezone));
    }
}
