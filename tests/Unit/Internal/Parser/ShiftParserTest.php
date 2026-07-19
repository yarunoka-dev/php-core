<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\Shift;
use Yarunoka\Internal\Parser\ShiftParser;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\Direction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ShiftParserTest extends TestCase
{
    #[Test]
    public function two_elements_become_an_exclusive_shift(): void
    {
        $this->assertEquals(
            new Shift(Direction::Prev, orSame: false, condition: CalendarWord::BusinessDay),
            ShiftParser::parse(['prev', 'business_day']),
        );
    }

    #[Test]
    public function three_elements_with_or_same_become_an_inclusive_shift(): void
    {
        $this->assertEquals(
            new Shift(Direction::Next, orSame: true, condition: CalendarWord::BusinessDay),
            ShiftParser::parse(['next', 'or_same', 'business_day']),
        );
    }

    #[Test]
    public function rejects_an_array_without_a_direction(): void
    {
        $this->expectException(InvalidYrnkException::class);

        ShiftParser::parse(['business_day']);
    }

    #[Test]
    public function rejects_or_same_in_the_wrong_position(): void
    {
        $this->expectException(InvalidYrnkException::class);

        ShiftParser::parse(['prev', 'business_day', 'or_same']);
    }

    #[Test]
    public function rejects_four_elements(): void
    {
        $this->expectException(InvalidYrnkException::class);

        ShiftParser::parse(['prev', 'or_same', 'business_day', 'fri']);
    }

    #[Test]
    public function rejects_a_non_array(): void
    {
        $this->expectException(InvalidYrnkException::class);

        ShiftParser::parse('prev');
    }
}
