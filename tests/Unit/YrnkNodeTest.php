<?php

namespace Yarunoka\Tests\Unit;

use Yarunoka\Calendar\Calendar;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\UnsupportedVersionException;
use Yarunoka\Expression\AllDay;
use Yarunoka\Yrnk;
use Yarunoka\YrnkSchedule;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YrnkNodeTest extends TestCase
{
    #[Test]
    public function a_document_holds_its_four_parts(): void
    {
        $document = new Yrnk(
            version: '1.0',
            timezone: new DateTimeZone('Asia/Tokyo'),
            calendar: new Calendar(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );

        $this->assertSame('1.0', $document->version);
        $this->assertSame('Asia/Tokyo', $document->timezone->getName());
        $this->assertCount(1, $document->schedules);
    }

    #[Test]
    public function rejects_an_unknown_version(): void
    {
        $this->expectException(UnsupportedVersionException::class);

        new Yrnk(
            version: '2.0',
            timezone: new DateTimeZone('Asia/Tokyo'),
            calendar: new Calendar(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );
    }

    #[Test]
    public function accepts_a_timezone_with_dst(): void
    {
        // The transition semantics (RFC 5545 §3.3.5) live on the
        // evaluating side (MatchFinder).
        $document = new Yrnk(
            version: '1.0',
            timezone: new DateTimeZone('Europe/London'),
            calendar: new Calendar(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );

        $this->assertSame('Europe/London', $document->timezone->getName());
    }

    #[Test]
    public function accepts_a_backward_link_timezone(): void
    {
        // Backward links are entries of the IANA tz database, and the
        // spec checks names against the implementation's tz database.
        $document = new Yrnk(
            version: '1.0',
            timezone: new DateTimeZone('Japan'),
            calendar: new Calendar(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );

        $this->assertSame('Japan', $document->timezone->getName());
    }

    #[Test]
    public function rejects_a_fixed_offset_timezone(): void
    {
        // PHP's DateTimeZone carries fixed offsets too, but the spec
        // limits timezone to IANA names (a document anchored to UTC
        // writes "UTC").
        $this->expectException(InvalidValueException::class);

        new Yrnk(
            version: '1.0',
            timezone: new DateTimeZone('+09:00'),
            calendar: new Calendar(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );
    }

    #[Test]
    public function rejects_a_timezone_abbreviation(): void
    {
        // JST constructs as a DateTimeZone abbreviation but is not a tz
        // database entry.
        $this->expectException(InvalidValueException::class);

        new Yrnk(
            version: '1.0',
            timezone: new DateTimeZone('JST'),
            calendar: new Calendar(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );
    }

    #[Test]
    public function rejects_empty_schedules(): void
    {
        $this->expectException(InvalidValueException::class);

        new Yrnk(
            version: '1.0',
            timezone: new DateTimeZone('Asia/Tokyo'),
            calendar: new Calendar(),
            schedules: [],
        );
    }
}
