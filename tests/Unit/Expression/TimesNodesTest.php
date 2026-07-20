<?php

namespace Yarunoka\Tests\Unit\Expression;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Expression\AllDay;
use Yarunoka\Expression\BusinessHourRef;
use Yarunoka\Expression\EveryGrid;
use Yarunoka\Expression\FixedTimes;
use Yarunoka\Expression\TimesSpec;
use Yarunoka\Time\TimeOfDay;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\TimeUnit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TimesNodesTest extends TestCase
{
    #[Test]
    public function fixed_times_keeps_the_written_order(): void
    {
        // Not normalized (sorted) so that round-tripping is the identity.
        // Sorting is evaluation's job.
        $times = new FixedTimes([TimeOfDay::fromString('12:00'), TimeOfDay::fromString('09:00')]);

        $this->assertSame(12 * 3600, $times->times[0]->secondsFromMidnight);
        $this->assertSame(9 * 3600, $times->times[1]->secondsFromMidnight);
    }

    #[Test]
    public function fixed_times_rejects_an_empty_enumeration(): void
    {
        $this->expectException(InvalidValueException::class);

        new FixedTimes([]);
    }

    #[Test]
    public function fixed_times_rejects_duplicates(): void
    {
        $this->expectException(InvalidValueException::class);

        new FixedTimes([TimeOfDay::fromString('09:00'), TimeOfDay::fromString('09:00')]);
    }

    #[Test]
    public function every_grid_keeps_the_count_and_the_unit_as_written(): void
    {
        // 90 minutes is not folded into 5400 seconds (so that
        // round-tripping is the identity).
        $grid = new EveryGrid(90, TimeUnit::Minute, between: null);

        $this->assertSame(90, $grid->amount);
        $this->assertSame(TimeUnit::Minute, $grid->unit);
        $this->assertNull($grid->between);
    }

    #[Test]
    public function every_grid_rejects_zero(): void
    {
        $this->expectException(InvalidValueException::class);

        new EveryGrid(0, TimeUnit::Hour, between: null);
    }

    #[Test]
    #[DataProvider('everyLimits')]
    public function every_grid_accepts_the_per_unit_maximum(int $amount, TimeUnit $unit): void
    {
        $grid = new EveryGrid($amount, $unit, between: null);

        $this->assertSame($amount, $grid->amount);
    }

    #[Test]
    #[DataProvider('everyLimits')]
    public function every_grid_rejects_exceeding_the_per_unit_maximum(int $amount, TimeUnit $unit): void
    {
        $this->expectException(InvalidValueException::class);

        new EveryGrid($amount + 1, $unit, between: null);
    }

    /**
     * @return array<string, array{int, TimeUnit}>
     */
    public static function everyLimits(): array
    {
        return [
            'hour' => [24, TimeUnit::Hour],
            'minute' => [1440, TimeUnit::Minute],
            'second' => [86400, TimeUnit::Second],
        ];
    }

    #[Test]
    public function every_grid_can_hold_a_window_pair_between(): void
    {
        $grid = new EveryGrid(1, TimeUnit::Hour, between: TimeWindow::fromStrings('08:00', '20:00'));

        $this->assertInstanceOf(TimeWindow::class, $grid->between);
    }

    #[Test]
    public function every_grid_can_hold_a_business_hour_reference_between(): void
    {
        $grid = new EveryGrid(1, TimeUnit::Hour, between: new BusinessHourRef());

        $this->assertInstanceOf(BusinessHourRef::class, $grid->between);
    }

    #[Test]
    public function every_time_specification_is_a_times_spec(): void
    {
        $this->assertInstanceOf(TimesSpec::class, new FixedTimes([TimeOfDay::fromString('09:00')]));
        $this->assertInstanceOf(TimesSpec::class, new EveryGrid(1, TimeUnit::Hour, between: null));
        $this->assertInstanceOf(TimesSpec::class, new AllDay());
    }
}
