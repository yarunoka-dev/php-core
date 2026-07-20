<?php

namespace Yarunoka\Tests\Unit\Internal\Builder;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Expression\AllDay;
use Yarunoka\Expression\BusinessHourRef;
use Yarunoka\Expression\EveryGrid;
use Yarunoka\Expression\FixedTimes;
use Yarunoka\Internal\Builder\TimesBuilder;
use Yarunoka\Time\TimeOfDay;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\TimeUnit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TimesBuilderTest extends TestCase
{
    #[Test]
    public function fixed_times_come_out_in_written_order(): void
    {
        $times = new FixedTimes([TimeOfDay::fromString('12:00'), TimeOfDay::fromString('09:00')]);

        $this->assertSame(['12:00', '09:00'], TimesBuilder::build($times));
    }

    #[Test]
    public function a_grid_becomes_every_and_between(): void
    {
        $plain = new EveryGrid(90, TimeUnit::Minute, between: null);
        $window = new EveryGrid(1, TimeUnit::Hour, between: TimeWindow::fromStrings('22:00', '24:00'));
        $ref = new EveryGrid(1, TimeUnit::Hour, between: new BusinessHourRef());

        $this->assertSame(['every' => [90, 'minute']], TimesBuilder::build($plain));
        $this->assertSame(
            ['every' => [1, 'hour'], 'between' => ['22:00', '24:00']],
            TimesBuilder::build($window),
        );
        $this->assertSame(
            ['every' => [1, 'hour'], 'between' => 'business_hour'],
            TimesBuilder::build($ref),
        );
    }

    #[Test]
    public function all_day_is_schedule_builders_job_and_raises(): void
    {
        $this->expectException(InvalidValueException::class);

        TimesBuilder::build(new AllDay());
    }
}
