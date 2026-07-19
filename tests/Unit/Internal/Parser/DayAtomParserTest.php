<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\CustomRef;
use Yarunoka\Expression\LastDayOfMonth;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\OrdinalWeekday;
use Yarunoka\Expression\Weekday;
use Yarunoka\Internal\Parser\DayAtomParser;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Ordinal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DayAtomParserTest extends TestCase
{
    #[Test]
    public function an_integer_becomes_a_month_day(): void
    {
        $this->assertEquals(new MonthDay(1), DayAtomParser::parse(1));
        $this->assertEquals(new MonthDay(31), DayAtomParser::parse(31));
    }

    #[Test]
    public function rejects_an_integer_out_of_range(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse(0);
    }

    #[Test]
    public function a_day_name_becomes_a_weekday(): void
    {
        $this->assertEquals(new Weekday(DayName::Mon), DayAtomParser::parse('mon'));
    }

    #[Test]
    public function calendar_vocabulary_returns_as_the_enum_itself(): void
    {
        $this->assertSame(CalendarWord::Holiday, DayAtomParser::parse('holiday'));
        $this->assertSame(CalendarWord::BusinessDay, DayAtomParser::parse('business_day'));
    }

    #[Test]
    public function the_end_of_month_word_becomes_last_day_of_month(): void
    {
        $this->assertEquals(new LastDayOfMonth, DayAtomParser::parse('last_day_of_month'));
    }

    #[Test]
    public function a_tuple_becomes_an_ordinal_weekday(): void
    {
        $this->assertEquals(
            new OrdinalWeekday(Ordinal::Third, DayName::Mon),
            DayAtomParser::parse(['3rd', 'mon']),
        );
    }

    #[Test]
    public function an_unknown_word_becomes_a_custom_ref(): void
    {
        $this->assertEquals(new CustomRef('fête-nationale'), DayAtomParser::parse('fête-nationale'));
    }

    #[Test]
    public function rejects_an_ordinal_word_outside_a_tuple(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse('3rd');
    }

    #[Test]
    public function rejects_a_modifier_word(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse('not');
    }

    #[Test]
    public function rejects_business_hour(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse('business_hour');
    }

    #[Test]
    public function rejects_a_date_shaped_literal(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse('2026-10-01');
    }

    #[Test]
    public function rejects_a_digits_only_string(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse('25');
    }

    #[Test]
    public function rejects_a_time_shaped_literal(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse('10:00');
    }

    #[Test]
    public function rejects_a_reversed_ordinal_tuple(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse(['mon', '3rd']);
    }

    #[Test]
    public function rejects_a_tuple_with_the_wrong_arity(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse(['3rd', 'mon', 'fri']);
    }

    #[Test]
    public function rejects_an_unsupported_type(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse(true);
    }

    #[Test]
    public function rejects_an_empty_string(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayAtomParser::parse('');
    }
}
