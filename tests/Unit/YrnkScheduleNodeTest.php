<?php

namespace Yarunoka\Tests\Unit;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Expression\AllDay;
use Yarunoka\Expression\DayExpression;
use Yarunoka\Expression\FixedTimes;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Time\TimeOfDay;
use Yarunoka\YrnkSchedule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YrnkScheduleNodeTest extends TestCase
{
    #[Test]
    public function a_schedule_holds_each_field(): void
    {
        $schedule = new YrnkSchedule(
            times: new FixedTimes([TimeOfDay::fromString('10:00')]),
            years: [2043],
            months: [6],
            days: new DayExpression([new MonthDay(15)]),
        );

        $this->assertSame([2043], $schedule->years);
        $this->assertSame([6], $schedule->months);
        $this->assertNotNull($schedule->days);
        $this->assertNull($schedule->shift);
        $this->assertNull($schedule->if);
    }

    #[Test]
    public function the_date_axes_can_be_omitted(): void
    {
        $schedule = new YrnkSchedule(times: new AllDay());

        $this->assertNull($schedule->years);
        $this->assertNull($schedule->months);
        $this->assertNull($schedule->days);
        $this->assertInstanceOf(AllDay::class, $schedule->times);
    }

    #[Test]
    public function rejects_empty_years(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkSchedule(times: new AllDay(), years: []);
    }

    #[Test]
    public function rejects_a_year_out_of_range(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkSchedule(times: new AllDay(), years: [0]);
    }

    #[Test]
    public function rejects_duplicate_years(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkSchedule(times: new AllDay(), years: [2043, 2043]);
    }

    #[Test]
    public function rejects_month_13(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkSchedule(times: new AllDay(), months: [13]);
    }

    #[Test]
    public function rejects_month_zero(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkSchedule(times: new AllDay(), months: [0]);
    }
}
