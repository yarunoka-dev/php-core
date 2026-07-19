<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Exceptions\ReservedNameException;
use Yarunoka\Internal\Parser\DefinitionsParser;
use Yarunoka\Time\LocalDate;
use Yarunoka\Vocabulary\DayName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DefinitionsParserTest extends TestCase
{
    #[Test]
    public function parses_a_date_list_of_a_built_in_definition(): void
    {
        $definitions = DefinitionsParser::parse(['holidays' => ['2026-01-01']]);

        $this->assertSame(['2026-01-01'], array_map(
            static fn (LocalDate $date): string => $date->toString(),
            $definitions->holidays->dates ?? [],
        ));
        $this->assertNull($definitions->businessHolidays);
    }

    #[Test]
    public function parses_a_resolver_name_reference(): void
    {
        $definitions = DefinitionsParser::parse(['business_days' => 'special-days']);

        $this->assertSame('special-days', $definitions->businessDays?->resolver);
    }

    #[Test]
    public function rejects_a_whitespace_only_resolver_name(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DefinitionsParser::parse(['business_days' => '   ']);
    }

    #[Test]
    public function parses_workweek_and_business_hours(): void
    {
        $definitions = DefinitionsParser::parse([
            'workweek' => ['tue', 'sat'],
            'business_hours' => [['09:00', '12:00'], ['13:00', '18:00']],
        ]);

        $this->assertSame([DayName::Tue, DayName::Sat], $definitions->workweek?->days);
        $this->assertCount(2, $definitions->businessHours->windows ?? []);
    }

    #[Test]
    public function parses_custom_values_and_validates_the_key_names(): void
    {
        $definitions = DefinitionsParser::parse([
            'custom' => ['founding-day' => ['2026-10-01'], 'garbage-day' => 'garbage-days'],
        ]);

        $this->assertNotNull($definitions->custom['founding-day']->dates);
        $this->assertSame('garbage-days', $definitions->custom['garbage-day']->resolver);
    }

    #[Test]
    public function rejects_an_unknown_key(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DefinitionsParser::parse(['holiday' => []]);
    }

    #[Test]
    public function rejects_a_single_date_string(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DefinitionsParser::parse(['holidays' => '2026-01-01']);
    }

    #[Test]
    public function rejects_a_single_date_string_in_custom_too(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DefinitionsParser::parse(['custom' => ['anniversary' => '2026-10-01']]);
    }

    #[Test]
    public function rejects_a_duplicated_date_list(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DefinitionsParser::parse(['holidays' => ['2026-01-01', '2026-01-01']]);
    }

    #[Test]
    public function rejects_a_reserved_word_as_a_custom_name(): void
    {
        $this->expectException(ReservedNameException::class);

        DefinitionsParser::parse(['custom' => ['holiday' => ['2026-01-01']]]);
    }

    #[Test]
    public function rejects_a_workweek_with_an_invalid_day_name(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DefinitionsParser::parse(['workweek' => ['monday']]);
    }

    #[Test]
    public function rejects_a_non_date_element(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DefinitionsParser::parse(['holidays' => [20260101]]);
    }

    #[Test]
    public function rejects_a_list_shaped_definitions(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DefinitionsParser::parse([['holidays' => []]]);
    }
}
