<?php

namespace Yarunoka\Tests\Unit\Builder;

use Yarunoka\Builder\YrnkBuilder;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Calendar\Holidays;
use Yarunoka\Expression\AllDay;
use Yarunoka\Yrnk;
use Yarunoka\YrnkSchedule;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YrnkBuilderTest extends TestCase
{
    #[Test]
    public function builds_the_four_parts_of_a_document_into_the_raw_dsl_shape(): void
    {
        $document = new Yrnk(
            version: '1.0',
            timezone: new DateTimeZone('Asia/Tokyo'),
            calendar: new Calendar(holidays: Holidays::ofDates([])),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );

        $this->assertSame([
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'calendar' => ['holidays' => []],
            'schedules' => [['allday' => true]],
        ], (new YrnkBuilder())->build($document));
    }

    #[Test]
    public function an_empty_calendar_is_omitted(): void
    {
        $document = new Yrnk(
            version: '1.0',
            timezone: new DateTimeZone('UTC'),
            calendar: new Calendar(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );

        $this->assertSame([
            'version' => '1.0',
            'timezone' => 'UTC',
            'schedules' => [['allday' => true]],
        ], (new YrnkBuilder())->build($document));
    }

    #[Test]
    public function to_json_is_the_json_representation_of_build(): void
    {
        $document = new Yrnk(
            version: '1.0',
            timezone: new DateTimeZone('UTC'),
            calendar: new Calendar(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );

        $this->assertSame(
            '{"version":"1.0","timezone":"UTC","schedules":[{"allday":true}]}',
            (new YrnkBuilder())->toJson($document),
        );
    }
}
