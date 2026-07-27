<?php

namespace Yarunoka\Tests\Unit;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\YrnkDate;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YrnkDateTest extends TestCase
{
    #[Test]
    public function builds_a_day_from_the_zero_padded_spelling(): void
    {
        $date = new YrnkDate('2026-07-14', new DateTimeZone('Asia/Tokyo'));

        $this->assertSame('2026-07-14', $date->format('Y-m-d'));
    }

    #[Test]
    public function is_a_date_time_immutable_so_it_can_be_handed_to_other_libraries(): void
    {
        $date = new YrnkDate('2026-07-14', new DateTimeZone('Asia/Tokyo'));

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertInstanceOf(DateTimeInterface::class, $date);
    }

    #[Test]
    public function carries_the_document_timezone(): void
    {
        $date = new YrnkDate('2026-07-14', new DateTimeZone('Asia/Tokyo'));

        $this->assertSame('Asia/Tokyo', $date->getTimezone()->getName());
        $this->assertSame('+09:00', $date->format('P'));
    }

    #[Test]
    public function stands_at_the_start_of_its_day(): void
    {
        $date = new YrnkDate('2026-07-14', new DateTimeZone('Asia/Tokyo'));

        $this->assertSame('00:00:00', $date->format('H:i:s'));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function spellingsThatAreNotADate(): array
    {
        return [
            'with a time' => ['2026-07-14 10:30'],
            'T separator' => ['2026-07-14T00:00'],
            'without zero padding' => ['2026-7-14'],
            'slash separated' => ['2026/07/14'],
            'relative wording' => ['tomorrow'],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('spellingsThatAreNotADate')]
    public function rejects_anything_that_is_not_the_yarunoka_date_spelling(string $spelling): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkDate($spelling, new DateTimeZone('Asia/Tokyo'));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function daysThatAreNotOnTheCalendar(): array
    {
        return [
            'thirty first of a short month' => ['2026-04-31'],
            'february thirtieth' => ['2026-02-30'],
            'february twenty ninth of a common year' => ['2025-02-29'],
            'month zero' => ['2026-00-10'],
            'month thirteen' => ['2026-13-01'],
        ];
    }

    #[Test]
    #[DataProvider('daysThatAreNotOnTheCalendar')]
    public function rejects_days_the_proleptic_gregorian_calendar_does_not_have(string $spelling): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkDate($spelling, new DateTimeZone('Asia/Tokyo'));
    }

    #[Test]
    public function keeps_the_day_when_midnight_is_skipped_by_a_transition(): void
    {
        // America/Santiago springs forward at midnight on 2026-09-06, so
        // 00:00 does not exist. RFC 5545 3.3.5 pushes the wall time
        // forward; the day itself is unaffected.
        $date = new YrnkDate('2026-09-06', new DateTimeZone('America/Santiago'));

        $this->assertSame('2026-09-06', $date->format('Y-m-d'));
        $this->assertSame('01:00:00', $date->format('H:i:s'));
    }

    #[Test]
    public function takes_the_resulting_day_when_the_whole_day_is_skipped(): void
    {
        // Pacific/Apia skipped 2011-12-30 outright when it moved across
        // the date line. The wall time pushes forward past the day
        // boundary, and the resulting day is what the value stands for.
        $date = new YrnkDate('2011-12-30', new DateTimeZone('Pacific/Apia'));

        $this->assertSame('2011-12-31', $date->format('Y-m-d'));
    }
}
