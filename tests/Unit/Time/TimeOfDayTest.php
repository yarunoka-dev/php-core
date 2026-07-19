<?php

namespace Yarunoka\Tests\Unit\Time;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Time\TimeOfDay;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class TimeOfDayTest extends TestCase
{
    #[Test]
    public function parses_a_valid_time_string_into_seconds_from_midnight(): void
    {
        $this->assertSame(0, TimeOfDay::fromString('00:00')->secondsFromMidnight);
        $this->assertSame(8 * 3600 + 30 * 60, TimeOfDay::fromString('08:30')->secondsFromMidnight);
        $this->assertSame(23 * 3600 + 59 * 60, TimeOfDay::fromString('23:59')->secondsFromMidnight);
    }

    #[Test]
    public function to_string_returns_the_hh_mm_notation_paired_with_from_string(): void
    {
        $this->assertSame('00:00', TimeOfDay::fromString('00:00')->toString());
        $this->assertSame('08:30', TimeOfDay::fromString('08:30')->toString());
        $this->assertSame('23:59', TimeOfDay::fromString('23:59')->toString());
    }

    #[Test]
    public function rejects_a_time_without_zero_padding(): void
    {
        $this->expectException(InvalidValueException::class);

        TimeOfDay::fromString('0:00');
    }

    #[Test]
    public function rejects_a_time_with_seconds(): void
    {
        $this->expectException(InvalidValueException::class);

        TimeOfDay::fromString('09:00:00');
    }

    #[Test]
    #[TestDox('rejects 24:00 as a time of day')]
    public function rejectsEndOfDayTokenAsTimeOfDay(): void
    {
        // "24:00" is a token allowed only as a window end. It is invalid
        // as a time of day.
        $this->expectException(InvalidValueException::class);

        TimeOfDay::fromString('24:00');
    }

    #[Test]
    public function rejects_a_minute_that_does_not_exist(): void
    {
        $this->expectException(InvalidValueException::class);

        TimeOfDay::fromString('09:60');
    }

    #[Test]
    public function rejects_a_non_numeric_string(): void
    {
        $this->expectException(InvalidValueException::class);

        TimeOfDay::fromString('ab:cd');
    }
}
