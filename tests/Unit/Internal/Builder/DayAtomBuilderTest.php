<?php

namespace Yarunoka\Tests\Unit\Internal\Builder;

use Yarunoka\Expression\CustomRef;
use Yarunoka\Expression\LastDayOfMonth;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\OrdinalWeekday;
use Yarunoka\Expression\Weekday;
use Yarunoka\Internal\Builder\DayAtomBuilder;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Ordinal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DayAtomBuilderTest extends TestCase
{
    #[Test]
    public function builds_every_atom_kind_into_its_raw_dsl_shape(): void
    {
        $this->assertSame(25, DayAtomBuilder::build(new MonthDay(25)));
        $this->assertSame('mon', DayAtomBuilder::build(new Weekday(DayName::Mon)));
        $this->assertSame('holiday', DayAtomBuilder::build(CalendarWord::Holiday));
        $this->assertSame(['3rd', 'mon'], DayAtomBuilder::build(new OrdinalWeekday(Ordinal::Third, DayName::Mon)));
        $this->assertSame('last_day_of_month', DayAtomBuilder::build(new LastDayOfMonth));
        $this->assertSame('fête-nationale', DayAtomBuilder::build(new CustomRef('fête-nationale')));
    }
}
