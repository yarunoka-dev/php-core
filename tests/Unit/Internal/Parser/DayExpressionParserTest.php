<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\Weekday;
use Yarunoka\Internal\Parser\DayExpressionParser;
use Yarunoka\Vocabulary\DayName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DayExpressionParserTest extends TestCase
{
    #[Test]
    public function delegates_elements_to_the_atom_parser_and_keeps_order(): void
    {
        $expression = DayExpressionParser::parse([25, 'mon']);

        $this->assertEquals(new MonthDay(25), $expression->atoms[0]);
        $this->assertEquals(new Weekday(DayName::Mon), $expression->atoms[1]);
    }

    #[Test]
    public function rejects_a_scalar(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayExpressionParser::parse('mon');
    }

    #[Test]
    public function rejects_an_empty_list(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayExpressionParser::parse([]);
    }

    #[Test]
    public function rejects_an_associative_array(): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayExpressionParser::parse(['a' => 'mon']);
    }
}
