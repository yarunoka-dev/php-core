<?php

namespace Yarunoka\Tests\Unit;

use Yarunoka\Definitions\Definitions;
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
            version: 1,
            timezone: new DateTimeZone('Asia/Tokyo'),
            definitions: new Definitions(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );

        $this->assertSame(1, $document->version);
        $this->assertSame('Asia/Tokyo', $document->timezone->getName());
        $this->assertCount(1, $document->schedules);
    }

    #[Test]
    public function rejects_an_unknown_version(): void
    {
        $this->expectException(UnsupportedVersionException::class);

        new Yrnk(
            version: 2,
            timezone: new DateTimeZone('Asia/Tokyo'),
            definitions: new Definitions(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );
    }

    #[Test]
    public function accepts_a_timezone_with_dst(): void
    {
        // The transition semantics (RFC 5545 §3.3.5) live on the
        // evaluating side (MatchFinder).
        $document = new Yrnk(
            version: 1,
            timezone: new DateTimeZone('Europe/London'),
            definitions: new Definitions(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );

        $this->assertSame('Europe/London', $document->timezone->getName());
    }

    #[Test]
    public function accepts_a_fixed_offset_timezone(): void
    {
        $document = new Yrnk(
            version: 1,
            timezone: new DateTimeZone('+09:00'),
            definitions: new Definitions(),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );

        $this->assertSame('+09:00', $document->timezone->getName());
    }

    #[Test]
    public function rejects_empty_schedules(): void
    {
        $this->expectException(InvalidValueException::class);

        new Yrnk(
            version: 1,
            timezone: new DateTimeZone('Asia/Tokyo'),
            definitions: new Definitions(),
            schedules: [],
        );
    }
}
