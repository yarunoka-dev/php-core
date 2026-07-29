<?php

namespace Yarunoka\Tests\Unit;

use Yarunoka\Calendar\YrnkCalendar;
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

    private function evaluator(): YrnkEvaluator
    {
        return new YrnkEvaluator(new YrnkCalendar(), new DateTimeZone('Asia/Tokyo'));
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
