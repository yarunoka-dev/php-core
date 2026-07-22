<?php

namespace Yarunoka\Tests\Unit\Internal\Builder;

use Yarunoka\Calendar\BusinessHours;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Calendar\CustomDefinition;
use Yarunoka\Calendar\Holidays;
use Yarunoka\Calendar\Workweek;
use Yarunoka\Exceptions\InvalidCalendarDataException;
use Yarunoka\Internal\Builder\CalendarBuilder;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\DayName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CalendarBuilderTest extends TestCase
{
    #[Test]
    public function builds_each_definition_into_its_raw_dsl_shape_omitting_null_keys(): void
    {
        $calendar = new Calendar(
            holidays: Holidays::ofDates(['2026-01-01']),
            workweek: new Workweek([DayName::Tue, DayName::Sat]),
            businessHours: new BusinessHours([TimeWindow::fromStrings('09:00', '18:00')]),
            custom: ['founding-day' => CustomDefinition::ofDates(['2026-10-01'])],
        );

        $this->assertSame([
            'holidays' => ['2026-01-01'],
            'workweek' => ['tue', 'sat'],
            'business_hours' => [['09:00', '18:00']],
            'custom' => ['founding-day' => ['2026-10-01']],
        ], CalendarBuilder::build($calendar));
    }

    #[Test]
    public function empty_definitions_become_empty(): void
    {
        $this->assertSame([], CalendarBuilder::build(new Calendar()));
    }

    #[Test]
    public function a_resolver_name_reference_comes_out_as_the_name_itself(): void
    {
        $calendar = new Calendar(holidays: Holidays::byResolver('yasumi-jp'));

        $this->assertSame(['holidays' => 'yasumi-jp'], CalendarBuilder::build($calendar));
    }

    #[Test]
    public function deferred_becomes_a_resolved_snapshot(): void
    {
        $calendar = new Calendar(
            holidays: Holidays::deferred(static fn(): array => ['2026-01-01']),
        );

        $this->assertSame(['holidays' => ['2026-01-01']], CalendarBuilder::build($calendar));
    }

    #[Test]
    public function a_contract_violation_of_deferred_raises(): void
    {
        $calendar = new Calendar(
            holidays: Holidays::deferred(static fn(): array => ['2026/01/01']),
        );

        $this->expectException(InvalidCalendarDataException::class);

        CalendarBuilder::build($calendar);
    }

    #[Test]
    public function deferred_returning_a_non_array_raises_too(): void
    {
        $calendar = new Calendar(
            holidays: Holidays::deferred(static fn(): string => 'not-an-array'),
        );

        $this->expectException(InvalidCalendarDataException::class);

        CalendarBuilder::build($calendar);
    }
}
