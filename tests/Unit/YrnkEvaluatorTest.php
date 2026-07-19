<?php

namespace Yarunoka\Tests\Unit;

use Yarunoka\Definitions\Definitions;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\YrnkEvaluator;
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
        $schedule = (new ScheduleParser)->parse(['days' => ['mon'], 'allday' => true]);
        $instant = new DateTimeImmutable('2026-07-19 16:00:00', new DateTimeZone('UTC'));

        $this->assertTrue($this->evaluator()->matches($schedule, $instant));
    }

    #[Test]
    public function accepts_any_implementation_of_date_time_interface(): void
    {
        $schedule = (new ScheduleParser)->parse(['days' => ['mon'], 'times' => ['09:00']]);
        $mutable = new DateTime('2026-07-20 09:00:00', new DateTimeZone('Asia/Tokyo'));

        $this->assertTrue($this->evaluator()->matches($schedule, $mutable));
        $this->assertTrue($this->evaluator()->hasMatchIn(
            $schedule,
            new DateTime('2026-07-20 08:00:00', new DateTimeZone('Asia/Tokyo')),
            $mutable,
        ));
    }

    private function evaluator(): YrnkEvaluator
    {
        return new YrnkEvaluator(new Definitions, new DateTimeZone('Asia/Tokyo'));
    }
}
