<?php

namespace Yarunoka\Tests\Unit\Internal\Builder;

use Yarunoka\Expression\Shift;
use Yarunoka\Internal\Builder\ShiftBuilder;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\Direction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ShiftBuilderTest extends TestCase
{
    #[Test]
    public function an_exclusive_shift_becomes_two_elements(): void
    {
        $shift = new Shift(Direction::Prev, orSame: false, condition: CalendarWord::BusinessDay);

        $this->assertSame(['prev', 'business_day'], ShiftBuilder::build($shift));
    }

    #[Test]
    public function an_inclusive_shift_becomes_three_elements_with_or_same(): void
    {
        $shift = new Shift(Direction::Next, orSame: true, condition: CalendarWord::BusinessDay);

        $this->assertSame(['next', 'or_same', 'business_day'], ShiftBuilder::build($shift));
    }
}
