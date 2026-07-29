<?php

namespace Yarunoka\Tests\Unit\Calendar;

use Yarunoka\Calendar\YrnkBusinessHours;
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkCustomDefinition;
use Yarunoka\Calendar\YrnkHolidays;
use Yarunoka\Calendar\YrnkWorkweek;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Tests\Support\CountingResolver;
use Yarunoka\YrnkDate;
use Yarunoka\Time\YrnkTimeWindow;
use Yarunoka\Vocabulary\YrnkDayName;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CalendarNodesTest extends TestCase
{
    // ---- date set definitions (YrnkHolidays stands in for the four
    // structurally identical types) ----

    #[Test]
    public function of_dates_holds_date_strings_as_a_list_of_local_dates(): void
    {
        $holidays = YrnkHolidays::ofDates(['2026-01-01', '2026-01-12'], self::utc());

        $this->assertSame(['2026-01-01', '2026-01-12'], array_map(
            static fn(YrnkDate $date): string => $date->format('Y-m-d'),
            $holidays->dates ?? [],
        ));
        $this->assertNull($holidays->resolver);
    }

    #[Test]
    public function of_dates_rejects_an_invalid_date(): void
    {
        $this->expectException(InvalidValueException::class);

        YrnkHolidays::ofDates(['2026-1-1'], self::utc());
    }

    #[Test]
    public function of_dates_takes_the_date_time_objects_an_application_already_holds(): void
    {
        $holidays = YrnkHolidays::ofDates(
            [new DateTimeImmutable('2026-01-01 09:30:15', self::utc()), '2026-01-12'],
            self::utc(),
        );

        $this->assertSame(['2026-01-01', '2026-01-12'], array_map(
            static fn(YrnkDate $date): string => $date->format('Y-m-d'),
            $holidays->dates ?? [],
        ));
    }

    #[Test]
    public function of_dates_reads_a_date_time_object_as_the_day_it_is_written_as(): void
    {
        // The instant is 2026-01-02 08:00 in Tokyo, but a calendar carries
        // wall-clock dates and defines no zone of its own, so the day the
        // value spells is the day it means.
        $holidays = YrnkHolidays::ofDates(
            [new DateTimeImmutable('2026-01-01 23:00', self::utc())],
            new DateTimeZone('Asia/Tokyo'),
        );

        $this->assertSame('2026-01-01', ($holidays->dates[0] ?? null)?->format('Y-m-d'));
    }

    #[Test]
    public function of_dates_puts_every_date_on_the_documents_clock(): void
    {
        $holidays = YrnkHolidays::ofDates(
            [new DateTimeImmutable('2026-01-01', self::utc()), '2026-01-02'],
            new DateTimeZone('Asia/Tokyo'),
        );

        foreach ($holidays->dates ?? [] as $date) {
            $this->assertSame('Asia/Tokyo', $date->getTimezone()->getName());
        }
    }

    #[Test]
    public function of_dates_sees_the_same_day_written_two_ways_as_a_duplicate(): void
    {
        $this->expectException(InvalidValueException::class);

        YrnkHolidays::ofDates(['2026-01-01', new DateTimeImmutable('2026-01-01', self::utc())], self::utc());
    }

    #[Test]
    public function by_resolver_holds_the_resolver_name(): void
    {
        $holidays = YrnkHolidays::byResolver('yasumi-jp');

        $this->assertSame('yasumi-jp', $holidays->resolver);
        $this->assertNull($holidays->dates);
    }

    #[Test]
    public function by_resolver_rejects_an_empty_name(): void
    {
        $this->expectException(InvalidValueException::class);

        YrnkHolidays::byResolver('');
    }

    #[Test]
    public function by_resolver_rejects_a_date_shaped_name(): void
    {
        // Date literals and resolver names are distinguished by shape, so
        // a date-shaped name is not allowed.
        $this->expectException(InvalidValueException::class);

        YrnkHolidays::byResolver('2026-01-01');
    }



    // ---- workweek ----

    #[Test]
    public function workweek_keeps_day_names_in_written_order(): void
    {
        $workweek = new YrnkWorkweek([YrnkDayName::Tue, YrnkDayName::Sat, YrnkDayName::Mon]);

        $this->assertSame([YrnkDayName::Tue, YrnkDayName::Sat, YrnkDayName::Mon], $workweek->days);
    }

    #[Test]
    public function workweek_rejects_an_empty_list(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkWorkweek([]);
    }

    #[Test]
    public function workweek_rejects_duplicates(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkWorkweek([YrnkDayName::Mon, YrnkDayName::Mon]);
    }

    // ---- business_hours ----

    #[Test]
    public function business_hours_keeps_windows_in_written_order(): void
    {
        $hours = new YrnkBusinessHours([
            YrnkTimeWindow::fromStrings('13:00', '18:00'),
            YrnkTimeWindow::fromStrings('09:00', '12:00'),
        ]);

        $this->assertSame(13 * 3600, $hours->windows[0]->startSeconds);
        $this->assertSame(9 * 3600, $hours->windows[1]->startSeconds);
    }

    #[Test]
    public function business_hours_rejects_an_empty_list(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkBusinessHours([]);
    }

    #[Test]
    public function business_hours_rejects_overlapping_windows(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkBusinessHours([
            YrnkTimeWindow::fromStrings('09:00', '13:00'),
            YrnkTimeWindow::fromStrings('12:00', '18:00'),
        ]);
    }

    #[Test]
    public function business_hours_accepts_touching_windows_as_they_do_not_overlap(): void
    {
        // The intervals are half-open [start, end), so a 12:00 end and a
        // 12:00 start do not overlap.
        $hours = new YrnkBusinessHours([
            YrnkTimeWindow::fromStrings('09:00', '12:00'),
            YrnkTimeWindow::fromStrings('12:00', '18:00'),
        ]);

        $this->assertCount(2, $hours->windows);
    }

    // ---- the definitions root ----

    #[Test]
    public function definitions_holds_each_definition_and_null_means_undefined(): void
    {
        $calendar = new YrnkCalendar(
            holidays: YrnkHolidays::byResolver('yasumi-jp'),
            businessHolidays: null,
            businessDays: null,
            workweek: null,
            businessHours: null,
            custom: ['founding-day' => YrnkCustomDefinition::ofDates(['2026-10-01'], self::utc())],
        );

        $this->assertSame('yasumi-jp', $calendar->holidays?->resolver);
        $this->assertNull($calendar->businessHolidays);
        $this->assertArrayHasKey('founding-day', $calendar->custom);
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
