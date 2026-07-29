<?php

namespace Yarunoka\Tests\Unit\Calendar;

use Yarunoka\Calendar\YrnkBusinessDaysDateSet;
use Yarunoka\Calendar\YrnkBusinessHolidaysDateSet;
use Yarunoka\Calendar\YrnkBusinessHours;
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkDateSet;
use Yarunoka\Calendar\YrnkHolidaysDateSet;
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
    // ---- the date set family ----

    #[Test]
    public function every_kind_of_date_set_is_a_date_set(): void
    {
        foreach ([
            YrnkHolidaysDateSet::ofDates([], self::utc()),
            YrnkBusinessHolidaysDateSet::ofDates([], self::utc()),
            YrnkBusinessDaysDateSet::ofDates([], self::utc()),
            YrnkDateSet::ofDates([], self::utc()),
        ] as $definition) {
            $this->assertInstanceOf(YrnkDateSet::class, $definition);
        }
    }

    #[Test]
    public function of_dates_hands_back_the_kind_it_was_called_on(): void
    {
        // What the built-in kinds are for: the shared implementation must
        // not flatten them into the base, or a position could not tell one
        // from another.
        $this->assertInstanceOf(YrnkHolidaysDateSet::class, YrnkHolidaysDateSet::ofDates([], self::utc()));
        $this->assertNotInstanceOf(YrnkHolidaysDateSet::class, YrnkDateSet::ofDates([], self::utc()));
    }

    #[Test]
    public function a_built_in_slot_refuses_another_kind(): void
    {
        $this->expectException(\TypeError::class);

        // @phpstan-ignore argument.type
        new YrnkCalendar(holidays: YrnkBusinessDaysDateSet::ofDates([], self::utc()));
    }

    // ---- date set definitions (YrnkHolidaysDateSet stands in for the four
    // structurally identical types) ----

    #[Test]
    public function of_dates_holds_date_strings_as_a_list_of_local_dates(): void
    {
        $holidays = YrnkHolidaysDateSet::ofDates(['2026-01-01', '2026-01-12'], self::utc());

        $this->assertSame(['2026-01-01', '2026-01-12'], array_map(
            static fn(YrnkDate $date): string => $date->format('Y-m-d'),
            $holidays->dates,
        ));
    }

    #[Test]
    public function of_dates_rejects_an_invalid_date(): void
    {
        $this->expectException(InvalidValueException::class);

        YrnkHolidaysDateSet::ofDates(['2026-1-1'], self::utc());
    }

    #[Test]
    public function of_dates_takes_the_date_time_objects_an_application_already_holds(): void
    {
        $holidays = YrnkHolidaysDateSet::ofDates(
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
        $holidays = YrnkHolidaysDateSet::ofDates(
            [new DateTimeImmutable('2026-01-01 23:00', self::utc())],
            new DateTimeZone('Asia/Tokyo'),
        );

        $this->assertSame('2026-01-01', ($holidays->dates[0] ?? null)?->format('Y-m-d'));
    }

    #[Test]
    public function of_dates_puts_every_date_on_the_documents_clock(): void
    {
        $holidays = YrnkHolidaysDateSet::ofDates(
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

        YrnkHolidaysDateSet::ofDates(['2026-01-01', new DateTimeImmutable('2026-01-01', self::utc())], self::utc());
    }

    #[Test]
    public function a_definition_can_be_the_name_of_what_resolves_it(): void
    {
        $calendar = new YrnkCalendar(holidays: 'yasumi-jp');

        $this->assertSame('yasumi-jp', $calendar->holidays);
    }

    #[Test]
    public function a_name_that_is_empty_or_whitespace_only_is_rejected(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkCalendar(holidays: '   ');
    }

    #[Test]
    public function a_date_shaped_name_is_rejected(): void
    {
        // Date literals and resolver names are distinguished by shape, so
        // a date-shaped name is not allowed.
        $this->expectException(InvalidValueException::class);

        new YrnkCalendar(holidays: '2026-01-01');
    }

    #[Test]
    public function a_reserved_word_is_rejected_as_a_name_too(): void
    {
        // All names share one namespace, so a name written where a date
        // list is expected is held to the same spelling rule as a
        // date_sets key.
        $this->expectException(InvalidValueException::class);

        new YrnkCalendar(holidays: 'mon');
    }

    #[Test]
    public function a_date_sets_entry_carries_its_dates(): void
    {
        $calendar = new YrnkCalendar(
            dateSets: ['garbage-day' => YrnkDateSet::ofDates(['2026-07-03'], self::utc())],
        );

        $this->assertSame('2026-07-03', $calendar->dateSets['garbage-day']->dates[0]->format('Y-m-d'));
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
            holidays: 'yasumi-jp',
            businessHolidays: null,
            businessDays: null,
            workweek: null,
            businessHours: null,
            dateSets: ['founding-day' => YrnkDateSet::ofDates(['2026-10-01'], self::utc())],
        );

        $this->assertSame('yasumi-jp', $calendar->holidays);
        $this->assertNull($calendar->businessHolidays);
        $this->assertArrayHasKey('founding-day', $calendar->dateSets);
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
