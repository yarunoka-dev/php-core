<?php

namespace Yarunoka\Tests\Unit\Builder;

use Yarunoka\Builder\ScheduleBuilder;
use Yarunoka\Expression\AllDay;
use Yarunoka\Expression\DayExpression;
use Yarunoka\Expression\FixedTimes;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Time\TimeOfDay;
use Yarunoka\YrnkSchedule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ScheduleBuilderTest extends TestCase
{
    #[Test]
    public function only_the_given_fields_come_out_in_the_raw_dsl_shape(): void
    {
        $schedule = new YrnkSchedule(
            times: new FixedTimes([TimeOfDay::fromString('10:00')]),
            years: [2043],
            days: new DayExpression([new MonthDay(15)]),
        );

        $this->assertSame([
            'years' => [2043],
            'days' => [15],
            'times' => ['10:00'],
        ], (new ScheduleBuilder)->build($schedule));
    }

    #[Test]
    public function all_day_becomes_the_allday_key(): void
    {
        $schedule = new YrnkSchedule(times: new AllDay);

        $this->assertSame(['allday' => true], (new ScheduleBuilder)->build($schedule));
    }
}
