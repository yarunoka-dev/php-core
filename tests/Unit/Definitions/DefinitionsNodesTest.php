<?php

namespace Yarunoka\Tests\Unit\Definitions;

use Yarunoka\Definitions\BusinessHours;
use Yarunoka\Definitions\CustomDefinition;
use Yarunoka\Definitions\Definitions;
use Yarunoka\Definitions\Holidays;
use Yarunoka\Definitions\Workweek;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Tests\Support\CountingResolver;
use Yarunoka\Time\LocalDate;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\DayName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DefinitionsNodesTest extends TestCase
{
    // ---- date set definitions (Holidays stands in for the four
    // structurally identical types) ----

    #[Test]
    public function of_dates_holds_date_strings_as_a_list_of_local_dates(): void
    {
        $holidays = Holidays::ofDates(['2026-01-01', '2026-01-12']);

        $this->assertSame(['2026-01-01', '2026-01-12'], array_map(
            static fn(LocalDate $date): string => $date->toString(),
            $holidays->dates ?? [],
        ));
        $this->assertNull($holidays->resolver);
        $this->assertNull($holidays->closure);
    }

    #[Test]
    public function of_dates_rejects_an_invalid_date(): void
    {
        $this->expectException(InvalidValueException::class);

        Holidays::ofDates(['2026-1-1']);
    }

    #[Test]
    public function by_resolver_holds_the_resolver_name(): void
    {
        $holidays = Holidays::byResolver('yasumi-jp');

        $this->assertSame('yasumi-jp', $holidays->resolver);
        $this->assertNull($holidays->dates);
    }

    #[Test]
    public function by_resolver_rejects_an_empty_name(): void
    {
        $this->expectException(InvalidValueException::class);

        Holidays::byResolver('');
    }

    #[Test]
    public function by_resolver_rejects_a_date_shaped_name(): void
    {
        // Date literals and resolver names are distinguished by shape, so
        // a date-shaped name is not allowed.
        $this->expectException(InvalidValueException::class);

        Holidays::byResolver('2026-01-01');
    }

    #[Test]
    public function deferred_holds_the_closure_unevaluated(): void
    {
        $calls = 0;
        $holidays = Holidays::deferred(function () use (&$calls): array {
            $calls++;

            return [];
        });

        $this->assertNotNull($holidays->closure);
        $this->assertSame(0, $calls);
    }

    #[Test]
    public function deferred_accepts_a_resolver_contract_instance_unevaluated(): void
    {
        $resolver = new CountingResolver(['2026-01-01']);

        $holidays = Holidays::deferred($resolver);

        $this->assertNotNull($holidays->closure);
        $this->assertSame(0, $resolver->calls);
        $this->assertSame(['2026-01-01'], ($holidays->closure)());
        $this->assertSame(1, $resolver->calls);
    }

    // ---- workweek ----

    #[Test]
    public function workweek_keeps_day_names_in_written_order(): void
    {
        $workweek = new Workweek([DayName::Tue, DayName::Sat, DayName::Mon]);

        $this->assertSame([DayName::Tue, DayName::Sat, DayName::Mon], $workweek->days);
    }

    #[Test]
    public function workweek_rejects_an_empty_list(): void
    {
        $this->expectException(InvalidValueException::class);

        new Workweek([]);
    }

    #[Test]
    public function workweek_rejects_duplicates(): void
    {
        $this->expectException(InvalidValueException::class);

        new Workweek([DayName::Mon, DayName::Mon]);
    }

    // ---- business_hours ----

    #[Test]
    public function business_hours_keeps_windows_in_written_order(): void
    {
        $hours = new BusinessHours([
            TimeWindow::fromStrings('13:00', '18:00'),
            TimeWindow::fromStrings('09:00', '12:00'),
        ]);

        $this->assertSame(13 * 3600, $hours->windows[0]->startSeconds);
        $this->assertSame(9 * 3600, $hours->windows[1]->startSeconds);
    }

    #[Test]
    public function business_hours_rejects_an_empty_list(): void
    {
        $this->expectException(InvalidValueException::class);

        new BusinessHours([]);
    }

    #[Test]
    public function business_hours_rejects_overlapping_windows(): void
    {
        $this->expectException(InvalidValueException::class);

        new BusinessHours([
            TimeWindow::fromStrings('09:00', '13:00'),
            TimeWindow::fromStrings('12:00', '18:00'),
        ]);
    }

    #[Test]
    public function business_hours_accepts_touching_windows_as_they_do_not_overlap(): void
    {
        // The intervals are half-open [start, end), so a 12:00 end and a
        // 12:00 start do not overlap.
        $hours = new BusinessHours([
            TimeWindow::fromStrings('09:00', '12:00'),
            TimeWindow::fromStrings('12:00', '18:00'),
        ]);

        $this->assertCount(2, $hours->windows);
    }

    // ---- the definitions root ----

    #[Test]
    public function definitions_holds_each_definition_and_null_means_undefined(): void
    {
        $definitions = new Definitions(
            holidays: Holidays::byResolver('yasumi-jp'),
            businessHolidays: null,
            businessDays: null,
            workweek: null,
            businessHours: null,
            custom: ['founding-day' => CustomDefinition::ofDates(['2026-10-01'])],
        );

        $this->assertSame('yasumi-jp', $definitions->holidays?->resolver);
        $this->assertNull($definitions->businessHolidays);
        $this->assertArrayHasKey('founding-day', $definitions->custom);
    }
}
