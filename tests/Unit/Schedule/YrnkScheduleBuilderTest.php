<?php

namespace Yarunoka\Tests\Unit\Schedule;

use Yarunoka\Schedule\YrnkScheduleBuilder;
use Yarunoka\Schedule\AllDay;
use Yarunoka\Schedule\DayExpression;
use Yarunoka\Schedule\FixedTimes;
use Yarunoka\Schedule\MonthDay;
use Yarunoka\Time\TimeOfDay;
use Yarunoka\YrnkSchedule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YrnkScheduleBuilderTest extends TestCase
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
        ], (new YrnkScheduleBuilder())->build($schedule));
    }

    #[Test]
    public function all_day_becomes_the_allday_key(): void
    {
        $schedule = new YrnkSchedule(times: new AllDay());

        $this->assertSame(['allday' => true], (new YrnkScheduleBuilder())->build($schedule));
    }
}
