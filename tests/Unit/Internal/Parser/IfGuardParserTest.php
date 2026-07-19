<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\IfGuard;
use Yarunoka\Expression\Weekday;
use Yarunoka\Internal\Parser\IfGuardParser;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Direction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class IfGuardParserTest extends TestCase
{
    #[Test]
    public function one_element_is_the_condition_alone(): void
    {
        $this->assertEquals(
            new IfGuard(null, negated: false, condition: new Weekday(DayName::Fri)),
            IfGuardParser::parse(['fri']),
        );
    }

    #[Test]
    public function two_elements_with_not(): void
    {
        $this->assertEquals(
            new IfGuard(null, negated: true, condition: CalendarWord::Holiday),
            IfGuardParser::parse(['not', 'holiday']),
        );
    }

    #[Test]
    public function two_elements_with_a_direction(): void
    {
        $this->assertEquals(
            new IfGuard(Direction::Next, negated: false, condition: CalendarWord::BusinessHoliday),
            IfGuardParser::parse(['next', 'business_holiday']),
        );
    }

    #[Test]
    public function three_elements_with_a_direction_and_not(): void
    {
        $this->assertEquals(
            new IfGuard(Direction::Prev, negated: true, condition: CalendarWord::Holiday),
            IfGuardParser::parse(['prev', 'not', 'holiday']),
        );
    }

    #[Test]
    public function rejects_same_as_a_direction(): void
    {
        $this->expectException(InvalidYrnkException::class);

        IfGuardParser::parse(['same', 'holiday']);
    }

    #[Test]
    #[TestDox('rejects a triple whose second element is not the not keyword')]
    public function rejectsTripleWhoseSecondElementIsNotTheNotKeyword(): void
    {
        $this->expectException(InvalidYrnkException::class);

        IfGuardParser::parse(['next', 'prev', 'holiday']);
    }

    #[Test]
    public function rejects_an_empty_array(): void
    {
        $this->expectException(InvalidYrnkException::class);

        IfGuardParser::parse([]);
    }

    #[Test]
    public function rejects_four_elements(): void
    {
        $this->expectException(InvalidYrnkException::class);

        IfGuardParser::parse(['next', 'not', 'holiday', 'fri']);
    }

    #[Test]
    public function rejects_a_non_array(): void
    {
        $this->expectException(InvalidYrnkException::class);

        IfGuardParser::parse('fri');
    }
}
