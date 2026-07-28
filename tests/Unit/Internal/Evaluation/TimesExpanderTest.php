<?php

namespace Yarunoka\Tests\Unit\Internal\Evaluation;

use Yarunoka\Calendar\YrnkBusinessHours;
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Schedule\AllDay;
use Yarunoka\Schedule\BusinessHourRef;
use Yarunoka\Schedule\EveryGrid;
use Yarunoka\Schedule\EverySequence;
use Yarunoka\Schedule\FixedTimes;
use Yarunoka\Schedule\TimesSpecInterface;
use Yarunoka\Internal\Evaluation\ResolvedCalendar;
use Yarunoka\Internal\Evaluation\TimesExpander;
use Yarunoka\Time\TimeOfDay;
use Yarunoka\Time\YrnkTimeWindow;
use Yarunoka\Internal\Vocabulary\TimeUnit;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TimesExpanderTest extends TestCase
{
    #[Test]
    public function fixed_times_are_sorted_into_ascending_order(): void
    {
        // The nodes keep the written order, so sorting happens here at
        // evaluation time.
        $times = new FixedTimes([TimeOfDay::fromString('12:00'), TimeOfDay::fromString('09:00')]);

        $this->assertSame([9 * 3600, 12 * 3600], $this->expander()->secondsOf($times));
    }

    #[Test]
    public function the_grid_anchors_at_the_window_start_and_excludes_the_half_open_end(): void
    {
        $times = new EveryGrid(1, TimeUnit::Hour, between: YrnkTimeWindow::fromStrings('08:30', '20:00'));

        $seconds = $this->expander()->secondsOf($times);

        $this->assertSame(8 * 3600 + 30 * 60, $seconds[0]);
        $this->assertSame(19 * 3600 + 30 * 60, $seconds[count($seconds) - 1]);
        $this->assertCount(12, $seconds);
    }

    #[Test]
    public function an_omitted_between_becomes_a_whole_day_grid(): void
    {
        $times = new EveryGrid(600, TimeUnit::Second, between: null);

        $seconds = $this->expander()->secondsOf($times);

        $this->assertCount(144, $seconds);
        $this->assertSame(0, $seconds[0]);
        $this->assertSame(86400 - 600, $seconds[143]);
    }

    #[Test]
    public function business_hour_lays_the_grid_per_window(): void
    {
        // No point during the lunch break (12:00–13:00).
        $expander = $this->expander(businessHours: [['09:00', '12:00'], ['13:00', '18:00']]);
        $times = new EveryGrid(1, TimeUnit::Hour, between: new BusinessHourRef());

        $this->assertSame(
            [9 * 3600, 10 * 3600, 11 * 3600, 13 * 3600, 14 * 3600, 15 * 3600, 16 * 3600, 17 * 3600],
            $expander->secondsOf($times),
        );
    }

    #[Test]
    #[DataProvider('theTimePartsThatCarryNoPointWithinADay')]
    public function a_time_part_that_lays_out_no_point_within_a_day_is_refused(TimesSpecInterface $times): void
    {
        // The finder answers allday by the day and the interval every by
        // its own arithmetic, so neither ever reaches the expander.
        $this->expectException(InvalidValueException::class);

        $this->expander()->secondsOf($times);
    }

    /**
     * @return array<string, array{TimesSpecInterface}>
     */
    public static function theTimePartsThatCarryNoPointWithinADay(): array
    {
        return [
            'allday' => [new AllDay()],
            'the interval every' => [new EverySequence(36, TimeUnit::Hour)],
        ];
    }

    // ---- helpers ----

    /**
     * @param  list<array{string, string}>|null  $businessHours
     */
    private function expander(?array $businessHours = null): TimesExpander
    {
        return new TimesExpander(new ResolvedCalendar(new YrnkCalendar(
            businessHours: $businessHours === null ? null : new YrnkBusinessHours(array_map(
                static fn(array $pair): YrnkTimeWindow => YrnkTimeWindow::fromStrings($pair[0], $pair[1]),
                $businessHours,
            )),
        ), resolvers: [], timezone: self::utc()));
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
