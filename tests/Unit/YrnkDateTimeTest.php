<?php

namespace Yarunoka\Tests\Unit;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\YrnkDateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YrnkDateTimeTest extends TestCase
{
    #[Test]
    public function builds_a_point_from_the_dsl_spelling(): void
    {
        $dateTime = new YrnkDateTime('2026-07-14 10:30', new DateTimeZone('Asia/Tokyo'));

        $this->assertSame('2026-07-14 10:30:00', $dateTime->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function fills_the_seconds_with_zero_when_the_spelling_omits_them(): void
    {
        $dateTime = new YrnkDateTime('2026-07-14 10:30', new DateTimeZone('Asia/Tokyo'));

        $this->assertSame('00', $dateTime->format('s'));
    }

    #[Test]
    public function builds_a_point_that_carries_seconds(): void
    {
        // Occurrences can land on a non-zero second: the interval every
        // accepts ["every", N, "second"] even though no literal in the DSL
        // can spell seconds.
        $dateTime = new YrnkDateTime('2026-07-14 10:02:03', new DateTimeZone('Asia/Tokyo'));

        $this->assertSame('2026-07-14 10:02:03', $dateTime->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function is_a_date_time_immutable_so_it_can_be_handed_to_other_libraries(): void
    {
        $dateTime = new YrnkDateTime('2026-07-14 10:30', new DateTimeZone('Asia/Tokyo'));

        $this->assertInstanceOf(DateTimeImmutable::class, $dateTime);
        $this->assertInstanceOf(DateTimeInterface::class, $dateTime);
    }

    #[Test]
    public function carries_the_document_timezone(): void
    {
        $dateTime = new YrnkDateTime('2026-07-14 10:30', new DateTimeZone('Asia/Tokyo'));

        $this->assertSame('Asia/Tokyo', $dateTime->getTimezone()->getName());
        $this->assertSame('+09:00', $dateTime->format('P'));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function spellingsThatAreNotAPoint(): array
    {
        return [
            'date only' => ['2026-07-14'],
            'T separator' => ['2026-07-14T10:30'],
            'without zero padding' => ['2026-7-14 10:30'],
            'hour twenty four' => ['2026-07-14 24:00'],
            'minute sixty' => ['2026-07-14 10:60'],
            'second sixty' => ['2026-07-14 10:30:60'],
            'fractional seconds' => ['2026-07-14 10:30:00.5'],
            'with an offset' => ['2026-07-14 10:30:00+09:00'],
            'relative wording' => ['tomorrow'],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('spellingsThatAreNotAPoint')]
    public function rejects_anything_that_is_not_the_yarunoka_point_spelling(string $spelling): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkDateTime($spelling, new DateTimeZone('Asia/Tokyo'));
    }

    #[Test]
    public function rejects_a_day_the_proleptic_gregorian_calendar_does_not_have(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkDateTime('2026-02-30 10:30', new DateTimeZone('Asia/Tokyo'));
    }

    #[Test]
    public function pushes_forward_when_the_wall_time_does_not_exist(): void
    {
        // America/Santiago springs forward at midnight on 2026-09-06.
        // RFC 5545 3.3.5 interprets the missing wall time with the offset
        // in effect before the transition.
        $dateTime = new YrnkDateTime('2026-09-06 00:00', new DateTimeZone('America/Santiago'));

        $this->assertSame('2026-09-06 01:00:00', $dateTime->format('Y-m-d H:i:s'));
    }
}
