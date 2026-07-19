<?php

namespace Yarunoka\Tests\Unit\Expression;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Expression\DayExpression;
use Yarunoka\Expression\IfGuard;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\Shift;
use Yarunoka\Expression\Weekday;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Direction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DayModifierNodesTest extends TestCase
{
    #[Test]
    public function day_expression_keeps_atoms_in_written_order(): void
    {
        $expression = new DayExpression([new MonthDay(5), new Weekday(DayName::Mon)]);

        $this->assertInstanceOf(MonthDay::class, $expression->atoms[0]);
        $this->assertInstanceOf(Weekday::class, $expression->atoms[1]);
    }

    #[Test]
    public function day_expression_rejects_an_empty_enumeration(): void
    {
        $this->expectException(InvalidValueException::class);

        new DayExpression([]);
    }

    #[Test]
    public function shift_holds_the_direction_the_inclusiveness_and_the_landing_condition(): void
    {
        $shift = new Shift(Direction::Prev, orSame: true, condition: CalendarWord::BusinessDay);

        $this->assertSame(Direction::Prev, $shift->direction);
        $this->assertTrue($shift->orSame);
        $this->assertSame(CalendarWord::BusinessDay, $shift->condition);
    }

    #[Test]
    public function if_guard_holds_the_direction_the_negation_and_the_condition(): void
    {
        $guard = new IfGuard(direction: null, negated: true, condition: CalendarWord::Holiday);

        $this->assertNull($guard->direction);
        $this->assertTrue($guard->negated);
        $this->assertSame(CalendarWord::Holiday, $guard->condition);
    }

    #[Test]
    public function if_guard_can_point_at_a_neighbouring_day(): void
    {
        $guard = new IfGuard(direction: Direction::Next, negated: false, condition: CalendarWord::BusinessHoliday);

        $this->assertSame(Direction::Next, $guard->direction);
    }
}
