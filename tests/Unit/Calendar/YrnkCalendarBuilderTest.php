<?php

namespace Yarunoka\Tests\Unit\Calendar;

use Yarunoka\Calendar\YrnkBusinessHours;
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkCustomDefinition;
use Yarunoka\Calendar\YrnkHolidays;
use Yarunoka\Calendar\YrnkWorkweek;
use Yarunoka\Exceptions\InvalidCalendarDataException;
use Yarunoka\Calendar\YrnkCalendarBuilder;
use Yarunoka\Time\YrnkTimeWindow;
use Yarunoka\Vocabulary\YrnkDayName;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YrnkCalendarBuilderTest extends TestCase
{
    #[Test]
    public function builds_each_definition_into_its_raw_dsl_shape_omitting_null_keys(): void
    {
        $calendar = new YrnkCalendar(
            holidays: YrnkHolidays::ofDates(['2026-01-01'], self::utc()),
            workweek: new YrnkWorkweek([YrnkDayName::Tue, YrnkDayName::Sat]),
            businessHours: new YrnkBusinessHours([YrnkTimeWindow::fromStrings('09:00', '18:00')]),
            custom: ['founding-day' => YrnkCustomDefinition::ofDates(['2026-10-01'], self::utc())],
        );

        $this->assertSame([
            'holidays' => ['2026-01-01'],
            'workweek' => ['tue', 'sat'],
            'business_hours' => [['09:00', '18:00']],
            'custom' => ['founding-day' => ['2026-10-01']],
        ], (new YrnkCalendarBuilder())->build($calendar, self::utc()));
    }

    #[Test]
    public function empty_definitions_become_empty(): void
    {
        $this->assertSame([], (new YrnkCalendarBuilder())->build(new YrnkCalendar(), self::utc()));
    }

    #[Test]
    public function a_resolver_name_reference_comes_out_as_the_name_itself(): void
    {
        $calendar = new YrnkCalendar(holidays: 'yasumi-jp');

        $this->assertSame(['holidays' => 'yasumi-jp'], (new YrnkCalendarBuilder())->build($calendar, self::utc()));
    }




    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
