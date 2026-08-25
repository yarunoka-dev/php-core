<?php

namespace Yarunoka\Tests\Unit;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkDateSet;
use Yarunoka\Exceptions\MalformedQueryException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Schedule\YrnkScheduleParser;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkParser;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The entry contract of YrnkEvaluator. The decision logic itself is
 * covered by the Internal/Evaluation units, and the decision semantics by
 * the Feature tests.
 */
class YrnkEvaluatorTest extends TestCase
{
    #[Test]
    public function the_date_time_of_matches_falls_onto_the_wall_date_of_the_configured_timezone(): void
    {
        // 16:00 UTC on 7/19 = 01:00 JST on 7/20 (a Monday).
        $schedule = (new YrnkScheduleParser())->parse(['days' => ['mon'], 'allday' => true], self::utc());
        $instant = new DateTimeImmutable('2026-07-19 16:00:00', new DateTimeZone('UTC'));

        $this->assertTrue($this->evaluator()->matches($schedule, $instant));
    }

    #[Test]
    public function accepts_any_implementation_of_date_time_interface(): void
    {
        $schedule = (new YrnkScheduleParser())->parse(['days' => ['mon'], 'times' => ['09:00']], self::utc());
        $mutable = new DateTime('2026-07-20 09:00:00', new DateTimeZone('Asia/Tokyo'));

        $this->assertTrue($this->evaluator()->matches($schedule, $mutable));
        $this->assertTrue($this->evaluator()->hasMatchIn(
            $schedule,
            new DateTime('2026-07-20 08:00:00', new DateTimeZone('Asia/Tokyo')),
            $mutable,
        ));
        $this->assertCount(1, $this->evaluator()->occurrencesIn(
            $schedule,
            new DateTime('2026-07-20 08:00:00', new DateTimeZone('Asia/Tokyo')),
            $mutable,
        ));
    }

    #[Test]
    public function a_parsed_document_is_enough_to_build_one(): void
    {
        $document = (new YrnkParser())->parse([
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'calendar' => ['date_sets' => ['founding-day' => ['2026-10-01']]],
            'schedules' => [['days' => ['founding-day'], 'times' => ['09:00']]],
        ]);

        $evaluator = YrnkEvaluator::fromYrnk($document);

        $this->assertTrue($evaluator->matches(
            $document->schedules[0],
            new DateTimeImmutable('2026-10-01 09:00:00', new DateTimeZone('Asia/Tokyo')),
        ));
    }

    #[Test]
    public function the_document_timezone_is_the_one_it_reads_by(): void
    {
        $document = (new YrnkParser())->parse([
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'schedules' => [['days' => ['mon'], 'allday' => true]],
        ]);

        // 16:00 UTC on 7/19 = 01:00 JST on 7/20 (a Monday).
        $this->assertTrue(YrnkEvaluator::fromYrnk($document)->matches(
            $document->schedules[0],
            new DateTimeImmutable('2026-07-19 16:00:00', new DateTimeZone('UTC')),
        ));
    }

    #[Test]
    public function ensure_resolvable_passes_schedules_whose_references_all_resolve(): void
    {
        // The check logic is ReferenceChecker's; this covers the public entry.
        $schedule = (new YrnkScheduleParser())->parse(['days' => ['founding-day'], 'times' => ['09:00']], self::utc());
        $evaluator = new YrnkEvaluator(
            new YrnkCalendar(dateSets: ['founding-day' => YrnkDateSet::ofDates(['2026-10-01'], self::utc())]),
            new DateTimeZone('Asia/Tokyo'),
        );

        $evaluator->ensureResolvable([$schedule]);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ensure_resolvable_raises_without_any_question_being_asked(): void
    {
        $this->expectException(UndefinedNameException::class);

        $schedule = (new YrnkScheduleParser())->parse(['days' => ['founding-day'], 'times' => ['09:00']], self::utc());

        $this->evaluator()->ensureResolvable([$schedule]);
    }

    #[Test]
    public function ensure_resolvable_checks_every_schedule_of_the_list(): void
    {
        $this->expectException(UndefinedNameException::class);

        $fine = (new YrnkScheduleParser())->parse(['days' => ['mon'], 'times' => ['09:00']], self::utc());
        $broken = (new YrnkScheduleParser())->parse(['days' => ['founding-day'], 'times' => ['09:00']], self::utc());

        $this->evaluator()->ensureResolvable([$fine, $broken]);
    }

    // ---- query well-formedness ----

    #[Test]
    public function a_reversed_period_is_a_malformed_query(): void
    {
        $schedule = (new YrnkScheduleParser())->parse(['days' => ['mon'], 'times' => ['09:00']], self::utc());

        $this->expectException(MalformedQueryException::class);

        $this->evaluator()->hasMatchIn(
            $schedule,
            new DateTimeImmutable('2026-07-21 00:00:00', self::utc()),
            new DateTimeImmutable('2026-07-20 00:00:00', self::utc()),
        );
    }

    #[Test]
    public function a_reversed_enumeration_is_a_malformed_query(): void
    {
        $schedule = (new YrnkScheduleParser())->parse(['days' => ['mon'], 'times' => ['09:00']], self::utc());

        $this->expectException(MalformedQueryException::class);

        $this->evaluator()->occurrencesIn(
            $schedule,
            new DateTimeImmutable('2026-07-21 00:00:00', self::utc()),
            new DateTimeImmutable('2026-07-20 00:00:00', self::utc()),
        );
    }

    #[Test]
    public function endpoints_reversed_by_less_than_a_second_are_malformed_too(): void
    {
        // The comparison is between the instants as given: nothing is
        // rounded, and equal means exactly equal.
        $schedule = (new YrnkScheduleParser())->parse(['days' => ['mon'], 'times' => ['09:00']], self::utc());

        $this->expectException(MalformedQueryException::class);

        $this->evaluator()->hasMatchIn(
            $schedule,
            new DateTimeImmutable('2026-07-20 00:00:00.500000', self::utc()),
            new DateTimeImmutable('2026-07-20 00:00:00.400000', self::utc()),
        );
    }

    #[Test]
    public function a_zero_width_period_is_legal_and_answers_false(): void
    {
        // A period over (t, t] holds no instant. Each judgment's "now" is
        // the next one's start, so a caller asking twice within the same
        // second must not be punished.
        $schedule = (new YrnkScheduleParser())->parse(['days' => ['mon'], 'times' => ['09:00']], self::utc());
        $instant = new DateTimeImmutable('2026-07-20 09:00:00', self::utc());

        $this->assertFalse($this->evaluator(self::utc())->hasMatchIn($schedule, $instant, $instant));
    }

    #[Test]
    public function a_zero_width_enumeration_is_legal_and_answers_the_point(): void
    {
        $schedule = (new YrnkScheduleParser())->parse(['days' => ['mon'], 'times' => ['09:00']], self::utc());
        $instant = new DateTimeImmutable('2026-07-20 09:00:00', self::utc());

        $this->assertCount(1, $this->evaluator(self::utc())->occurrencesIn($schedule, $instant, $instant));
    }

    private function evaluator(?DateTimeZone $timezone = null): YrnkEvaluator
    {
        return new YrnkEvaluator(new YrnkCalendar(), $timezone ?? new DateTimeZone('Asia/Tokyo'));
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
