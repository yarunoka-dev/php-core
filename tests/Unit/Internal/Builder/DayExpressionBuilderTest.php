<?php

namespace Yarunoka\Tests\Unit\Internal\Builder;

use Yarunoka\Expression\DayExpression;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\Weekday;
use Yarunoka\Internal\Builder\DayExpressionBuilder;
use Yarunoka\Vocabulary\DayName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DayExpressionBuilderTest extends TestCase
{
    #[Test]
    public function builds_the_enumeration_into_its_raw_dsl_shape_keeping_order(): void
    {
        $expression = new DayExpression([new Weekday(DayName::Fri), new MonthDay(5)]);

        $this->assertSame(['fri', 5], DayExpressionBuilder::build($expression));
    }
}
