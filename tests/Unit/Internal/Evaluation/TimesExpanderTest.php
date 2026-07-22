<?php

namespace Yarunoka\Tests\Unit\Internal\Evaluation;

use Yarunoka\Calendar\BusinessHours;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Expression\AllDay;
use Yarunoka\Expression\BusinessHourRef;
use Yarunoka\Expression\EveryGrid;
use Yarunoka\Expression\FixedTimes;
use Yarunoka\Internal\Evaluation\ResolvedCalendar;
use Yarunoka\Internal\Evaluation\TimesExpander;
use Yarunoka\Time\TimeOfDay;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\TimeUnit;
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
        $times = new EveryGrid(1, TimeUnit::Hour, between: TimeWindow::fromStrings('08:30', '20:00'));

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
    public function all_day_becomes_the_single_point_at_the_start_of_the_day(): void
    {
        $this->assertSame([0], $this->expander()->secondsOf(new AllDay()));
    }

    // ---- helpers ----

    /**
     * @param  list<array{string, string}>|null  $businessHours
     */
    private function expander(?array $businessHours = null): TimesExpander
    {
        return new TimesExpander(new ResolvedCalendar(new Calendar(
            businessHours: $businessHours === null ? null : new BusinessHours(array_map(
                static fn(array $pair): TimeWindow => TimeWindow::fromStrings($pair[0], $pair[1]),
                $businessHours,
            )),
        ), resolvers: []));
    }
}
