<?php

namespace Yarunoka\Tests\Unit\Time;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Time\TimeWindow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TimeWindowTest extends TestCase
{
    #[Test]
    public function holds_start_and_end_as_seconds(): void
    {
        $window = TimeWindow::fromStrings('08:30', '20:00');

        $this->assertSame(8 * 3600 + 30 * 60, $window->startSeconds);
        $this->assertSame(20 * 3600, $window->endSeconds);
    }

    #[Test]
    public function accepts_24_00_as_the_end_of_the_day_for_the_end(): void
    {
        $window = TimeWindow::fromStrings('22:00', '24:00');

        $this->assertSame(24 * 3600, $window->endSeconds);
    }

    #[Test]
    public function to_strings_returns_the_pair_notation_paired_with_from_strings(): void
    {
        $this->assertSame(['08:30', '20:00'], TimeWindow::fromStrings('08:30', '20:00')->toStrings());
        $this->assertSame(['22:00', '24:00'], TimeWindow::fromStrings('22:00', '24:00')->toStrings());
    }

    #[Test]
    public function rejects_24_00_as_the_start(): void
    {
        $this->expectException(InvalidValueException::class);

        TimeWindow::fromStrings('24:00', '24:00');
    }

    #[Test]
    public function rejects_a_window_whose_start_equals_its_end(): void
    {
        // The interval is half-open [start, end), so equal times mean an
        // empty window.
        $this->expectException(InvalidValueException::class);

        TimeWindow::fromStrings('12:00', '12:00');
    }

    #[Test]
    public function rejects_a_window_that_crosses_midnight(): void
    {
        $this->expectException(InvalidValueException::class);

        TimeWindow::fromStrings('22:00', '06:00');
    }
}
