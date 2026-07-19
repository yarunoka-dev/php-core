<?php

namespace Yarunoka\Tests\Unit\Time;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Time\LocalDateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LocalDateTimeTest extends TestCase
{
    #[Test]
    public function parses_a_valid_date_time_string(): void
    {
        $dateTime = LocalDateTime::fromString('2026-07-14 10:30');

        $this->assertSame('2026-07-14', $dateTime->date->toString());
        $this->assertSame(10 * 3600 + 30 * 60, $dateTime->secondsFromMidnight);
    }

    #[Test]
    public function parses_the_edges_of_the_day(): void
    {
        $this->assertSame(0, LocalDateTime::fromString('2026-07-14 00:00')->secondsFromMidnight);
        $this->assertSame(23 * 3600 + 59 * 60, LocalDateTime::fromString('2026-07-14 23:59')->secondsFromMidnight);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function invalidStrings(): array
    {
        return [
            'date only' => ['2026-07-14'],
            'T separator' => ['2026-07-14T10:30'],
            'with seconds' => ['2026-07-14 10:30:00'],
            '24 hours' => ['2026-07-14 24:00'],
            'date without zero padding' => ['2026-7-14 10:30'],
            'time without zero padding' => ['2026-07-14 9:30'],
            'two-space separator' => ['2026-07-14  10:30'],
            'trailing newline' => ["2026-07-14 10:30\n"],
            'empty string' => [''],
        ];
    }

    #[Test]
    #[DataProvider('invalidStrings')]
    public function rejects_any_spelling_other_than_the_single_format(string $raw): void
    {
        $this->expectException(InvalidValueException::class);

        LocalDateTime::fromString($raw);
    }

    #[Test]
    public function rejects_a_nonexistent_date_even_when_the_shape_matches(): void
    {
        $this->expectException(InvalidValueException::class);

        LocalDateTime::fromString('2026-02-30 10:00');
    }

    #[Test]
    public function to_string_is_the_identity_of_from_string(): void
    {
        $this->assertSame('2026-07-14 10:30', LocalDateTime::fromString('2026-07-14 10:30')->toString());
        $this->assertSame('2026-07-14 00:00', LocalDateTime::fromString('2026-07-14 00:00')->toString());
    }

    #[Test]
    public function is_before_compares_date_and_time_lexicographically(): void
    {
        $base = LocalDateTime::fromString('2026-07-14 10:30');

        $this->assertTrue($base->isBefore(LocalDateTime::fromString('2026-07-14 10:31')));
        $this->assertTrue($base->isBefore(LocalDateTime::fromString('2026-07-15 00:00')));
        $this->assertFalse($base->isBefore(LocalDateTime::fromString('2026-07-14 10:30')));
        $this->assertFalse($base->isBefore(LocalDateTime::fromString('2026-07-14 10:29')));
        $this->assertFalse($base->isBefore(LocalDateTime::fromString('2026-07-13 23:59')));
    }

    #[Test]
    public function to_instant_becomes_an_instant_in_the_given_timezone(): void
    {
        $instant = LocalDateTime::fromString('2026-07-14 10:30')->toInstant(new DateTimeZone('Asia/Tokyo'));

        $this->assertSame('2026-07-14T10:30:00+09:00', $instant->format('Y-m-d\TH:i:sP'));
    }

    #[Test]
    public function to_instant_rolls_a_nonexistent_time_forward_across_a_dst_gap(): void
    {
        // America/New_York 2026-03-08 has the spring transition 02:00 →
        // 03:00. Same rule as occurrences.
        $instant = LocalDateTime::fromString('2026-03-08 02:30')->toInstant(new DateTimeZone('America/New_York'));

        $this->assertSame('2026-03-08T03:30:00-04:00', $instant->format('Y-m-d\TH:i:sP'));
    }
}
