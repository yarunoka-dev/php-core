<?php

namespace Yarunoka\Tests\Unit\Internal\Builder;

use Yarunoka\Expression\IfGuard;
use Yarunoka\Expression\Weekday;
use Yarunoka\Internal\Builder\IfGuardBuilder;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Direction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IfGuardBuilderTest extends TestCase
{
    #[Test]
    public function builds_the_four_forms_into_their_raw_dsl_shapes(): void
    {
        $this->assertSame(['fri'], IfGuardBuilder::build(
            new IfGuard(null, negated: false, condition: new Weekday(DayName::Fri)),
        ));
        $this->assertSame(['not', 'holiday'], IfGuardBuilder::build(
            new IfGuard(null, negated: true, condition: CalendarWord::Holiday),
        ));
        $this->assertSame(['next', 'business_holiday'], IfGuardBuilder::build(
            new IfGuard(Direction::Next, negated: false, condition: CalendarWord::BusinessHoliday),
        ));
        $this->assertSame(['prev', 'not', 'holiday'], IfGuardBuilder::build(
            new IfGuard(Direction::Prev, negated: true, condition: CalendarWord::Holiday),
        ));
    }
}
