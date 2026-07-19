<?php

namespace Yarunoka\Tests\Unit\Builder;

use Yarunoka\Builder\YrnkBuilder;
use Yarunoka\Definitions\Definitions;
use Yarunoka\Definitions\Holidays;
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
            version: 1,
            timezone: new DateTimeZone('Asia/Tokyo'),
            definitions: new Definitions(holidays: Holidays::ofDates([])),
            schedules: [new YrnkSchedule(times: new AllDay)],
        );

        $this->assertSame([
            'version' => 1,
            'timezone' => 'Asia/Tokyo',
            'definitions' => ['holidays' => []],
            'schedules' => [['allday' => true]],
        ], (new YrnkBuilder)->build($document));
    }

    #[Test]
    public function empty_definitions_are_omitted(): void
    {
        $document = new Yrnk(
            version: 1,
            timezone: new DateTimeZone('UTC'),
            definitions: new Definitions,
            schedules: [new YrnkSchedule(times: new AllDay)],
        );

        $this->assertSame([
            'version' => 1,
            'timezone' => 'UTC',
            'schedules' => [['allday' => true]],
        ], (new YrnkBuilder)->build($document));
    }

    #[Test]
    public function to_json_is_the_json_representation_of_build(): void
    {
        $document = new Yrnk(
            version: 1,
            timezone: new DateTimeZone('UTC'),
            definitions: new Definitions,
            schedules: [new YrnkSchedule(times: new AllDay)],
        );

        $this->assertSame(
            '{"version":1,"timezone":"UTC","schedules":[{"allday":true}]}',
            (new YrnkBuilder)->toJson($document),
        );
    }
}
