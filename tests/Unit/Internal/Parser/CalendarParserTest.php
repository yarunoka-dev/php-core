<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Exceptions\ReservedNameException;
use Yarunoka\Internal\Parser\CalendarParser;
use Yarunoka\YrnkDate;
use Yarunoka\Vocabulary\YrnkDayName;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CalendarParserTest extends TestCase
{
    #[Test]
    public function parses_a_date_list_of_a_built_in_definition(): void
    {
        $calendar = CalendarParser::parse(['holidays' => ['2026-01-01']], self::utc());

        $this->assertSame(['2026-01-01'], array_map(
            static fn(YrnkDate $date): string => $date->format('Y-m-d'),
            $calendar->holidays->dates ?? [],
        ));
        $this->assertNull($calendar->businessHolidays);
    }

    #[Test]
    public function parses_a_resolver_name_reference(): void
    {
        $calendar = CalendarParser::parse(['business_days' => 'special-days'], self::utc());

        $this->assertSame('special-days', $calendar->businessDays?->resolver);
    }

    #[Test]
    public function rejects_a_whitespace_only_resolver_name(): void
    {
        $this->expectException(InvalidYrnkException::class);

        CalendarParser::parse(['business_days' => '   '], self::utc());
    }

    #[Test]
    public function parses_workweek_and_business_hours(): void
    {
        $calendar = CalendarParser::parse([
            'workweek' => ['tue', 'sat'],
            'business_hours' => [['09:00', '12:00'], ['13:00', '18:00']],
        ], self::utc());

        $this->assertSame([YrnkDayName::Tue, YrnkDayName::Sat], $calendar->workweek?->days);
        $this->assertCount(2, $calendar->businessHours->windows ?? []);
    }

    #[Test]
    public function parses_custom_values_and_validates_the_key_names(): void
    {
        $calendar = CalendarParser::parse([
            'custom' => ['founding-day' => ['2026-10-01'], 'garbage-day' => 'garbage-days'],
        ], self::utc());

        $this->assertNotNull($calendar->custom['founding-day']->dates);
        $this->assertSame('garbage-days', $calendar->custom['garbage-day']->resolver);
    }

    #[Test]
    public function rejects_an_unknown_key(): void
    {
        $this->expectException(InvalidYrnkException::class);

        CalendarParser::parse(['holiday' => []], self::utc());
    }

    #[Test]
    public function rejects_a_single_date_string(): void
    {
        $this->expectException(InvalidYrnkException::class);

        CalendarParser::parse(['holidays' => '2026-01-01'], self::utc());
    }

    #[Test]
    public function rejects_a_single_date_string_in_custom_too(): void
    {
        $this->expectException(InvalidYrnkException::class);

        CalendarParser::parse(['custom' => ['anniversary' => '2026-10-01']], self::utc());
    }

    #[Test]
    public function rejects_a_duplicated_date_list(): void
    {
        $this->expectException(InvalidYrnkException::class);

        CalendarParser::parse(['holidays' => ['2026-01-01', '2026-01-01']], self::utc());
    }

    #[Test]
    public function rejects_a_reserved_word_as_a_custom_name(): void
    {
        $this->expectException(ReservedNameException::class);

        CalendarParser::parse(['custom' => ['holiday' => ['2026-01-01']]], self::utc());
    }

    #[Test]
    public function rejects_a_workweek_with_an_invalid_day_name(): void
    {
        $this->expectException(InvalidYrnkException::class);

        CalendarParser::parse(['workweek' => ['monday']], self::utc());
    }

    #[Test]
    public function rejects_a_non_date_element(): void
    {
        $this->expectException(InvalidYrnkException::class);

        CalendarParser::parse(['holidays' => [20260101]], self::utc());
    }

    #[Test]
    public function rejects_a_list_shaped_definitions(): void
    {
        $this->expectException(InvalidYrnkException::class);

        CalendarParser::parse([['holidays' => []]], self::utc());
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
