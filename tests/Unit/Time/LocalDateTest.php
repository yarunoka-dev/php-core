<?php

namespace Yarunoka\Tests\Unit\Time;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Time\LocalDate;
use Yarunoka\Vocabulary\DayName;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LocalDateTest extends TestCase
{
    #[Test]
    public function parses_a_valid_date_string(): void
    {
        $date = LocalDate::fromString('2026-07-12');

        $this->assertSame(2026, $date->year);
        $this->assertSame(7, $date->month);
        $this->assertSame(12, $date->day);
    }

    #[Test]
    public function rejects_a_date_string_without_zero_padding(): void
    {
        $this->expectException(InvalidValueException::class);

        LocalDate::fromString('2026-7-1');
    }

    #[Test]
    public function rejects_a_slash_separated_date_string(): void
    {
        $this->expectException(InvalidValueException::class);

        LocalDate::fromString('2026/07/12');
    }

    #[Test]
    public function rejects_a_date_that_does_not_exist(): void
    {
        $this->expectException(InvalidValueException::class);

        LocalDate::fromString('2026-02-30');
    }

    #[Test]
    public function rejects_a_month_that_does_not_exist(): void
    {
        $this->expectException(InvalidValueException::class);

        LocalDate::fromString('2026-13-01');
    }

    #[Test]
    public function parses_february_29_in_a_leap_year(): void
    {
        $date = LocalDate::fromString('2024-02-29');

        $this->assertSame(29, $date->day);
    }

    #[Test]
    public function rejects_february_29_in_a_common_year(): void
    {
        $this->expectException(InvalidValueException::class);

        LocalDate::fromString('2026-02-29');
    }

    #[Test]
    public function returns_the_day_of_week(): void
    {
        $this->assertSame(DayName::Thu, LocalDate::fromString('2026-01-01')->dayOfWeek());
        $this->assertSame(DayName::Sun, LocalDate::fromString('2026-07-12')->dayOfWeek());
        $this->assertSame(DayName::Thu, LocalDate::fromString('2024-02-29')->dayOfWeek());
    }

    #[Test]
    public function returns_the_number_of_days_in_the_month(): void
    {
        $this->assertSame(31, LocalDate::fromString('2026-01-15')->daysInMonth());
        $this->assertSame(28, LocalDate::fromString('2026-02-15')->daysInMonth());
        $this->assertSame(29, LocalDate::fromString('2024-02-15')->daysInMonth());
        $this->assertSame(30, LocalDate::fromString('2026-04-15')->daysInMonth());
    }

    #[Test]
    public function adding_days_can_cross_a_month_boundary(): void
    {
        $date = LocalDate::fromString('2026-01-31')->addDays(1);

        $this->assertSame('2026-02-01', $date->toString());
    }

    #[Test]
    public function subtracting_days_can_cross_a_year_boundary(): void
    {
        $date = LocalDate::fromString('2026-01-01')->addDays(-1);

        $this->assertSame('2025-12-31', $date->toString());
    }

    #[Test]
    public function equal_dates_are_equal_by_equals(): void
    {
        $this->assertTrue(LocalDate::fromString('2026-07-12')->equals(LocalDate::fromString('2026-07-12')));
        $this->assertFalse(LocalDate::fromString('2026-07-12')->equals(LocalDate::fromString('2026-07-13')));
    }

    #[Test]
    public function is_after_compares_in_calendar_order(): void
    {
        $jul12 = LocalDate::fromString('2026-07-12');
        $jul13 = LocalDate::fromString('2026-07-13');

        $this->assertTrue($jul13->isAfter($jul12));
        $this->assertFalse($jul12->isAfter($jul13));
        $this->assertFalse($jul12->isAfter($jul12));
    }

    #[Test]
    public function days_until_returns_the_signed_difference_in_days(): void
    {
        $jul14 = LocalDate::fromString('2026-07-14');

        $this->assertSame(0, $jul14->daysUntil(LocalDate::fromString('2026-07-14')));
        $this->assertSame(1, $jul14->daysUntil(LocalDate::fromString('2026-07-15')));
        $this->assertSame(-1, $jul14->daysUntil(LocalDate::fromString('2026-07-13')));
        $this->assertSame(18, $jul14->daysUntil(LocalDate::fromString('2026-08-01')));
    }

    #[Test]
    public function days_until_counts_by_the_calendar_across_a_leap_day(): void
    {
        $this->assertSame(2, LocalDate::fromString('2024-02-28')->daysUntil(LocalDate::fromString('2024-03-01')));
        $this->assertSame(1, LocalDate::fromString('2026-02-28')->daysUntil(LocalDate::fromString('2026-03-01')));
    }

    #[Test]
    public function from_date_time_extracts_the_wall_date_in_the_date_times_timezone(): void
    {
        // 14:30 UTC = 23:30 in Tokyo. The date is read in the timezone of
        // the DateTime that was passed in.
        $utc = new DateTimeImmutable('2026-01-01 14:30:00', new DateTimeZone('UTC'));
        $tokyo = $utc->setTimezone(new DateTimeZone('Asia/Tokyo'));

        $this->assertSame('2026-01-01', LocalDate::fromDateTime($utc)->toString());
        $this->assertSame('2026-01-01', LocalDate::fromDateTime($tokyo)->toString());

        // 0:30 in Tokyo is still the previous day in UTC.
        $lateUtc = new DateTimeImmutable('2026-01-01 15:30:00', new DateTimeZone('UTC'));

        $this->assertSame('2026-01-02', LocalDate::fromDateTime($lateUtc->setTimezone(new DateTimeZone('Asia/Tokyo')))->toString());
    }

    #[Test]
    public function at_time_builds_an_instant_in_the_given_timezone(): void
    {
        $date = LocalDate::fromString('2026-07-12');

        $instant = $date->atTime(10 * 3600 + 30 * 60, new DateTimeZone('Asia/Tokyo'));

        $this->assertSame('2026-07-12T10:30:00+09:00', $instant->format('Y-m-d\TH:i:sP'));
    }

    #[Test]
    public function at_time_builds_the_boundaries_at_midnight_and_the_next_midnight(): void
    {
        $tokyo = new DateTimeZone('Asia/Tokyo');
        $date = LocalDate::fromString('2026-07-24');

        $this->assertSame('2026-07-24T00:00:00+09:00', $date->atTime(0, $tokyo)->format('Y-m-d\TH:i:sP'));
        $this->assertSame('2026-07-25T00:00:00+09:00', $date->atTime(86400, $tokyo)->format('Y-m-d\TH:i:sP'));
    }

    #[Test]
    public function at_time_rolls_a_nonexistent_time_forward_across_a_dst_gap(): void
    {
        // America/New_York 2026-03-08 has the spring transition 02:00 →
        // 03:00, so 02:30 does not exist.
        $instant = LocalDate::fromString('2026-03-08')
            ->atTime(2 * 3600 + 30 * 60, new DateTimeZone('America/New_York'));

        $this->assertSame('2026-03-08T03:30:00-04:00', $instant->format('Y-m-d\TH:i:sP'));
    }

    #[Test]
    public function at_time_picks_the_first_occurrence_in_a_dst_overlap(): void
    {
        // 2026-11-01 has the fall transition 02:00 → 01:00, so 01:30 occurs
        // twice; the EDT side (the first) is picked.
        $instant = LocalDate::fromString('2026-11-01')
            ->atTime(3600 + 30 * 60, new DateTimeZone('America/New_York'));

        $this->assertSame('2026-11-01T01:30:00-04:00', $instant->format('Y-m-d\TH:i:sP'));
    }
}
