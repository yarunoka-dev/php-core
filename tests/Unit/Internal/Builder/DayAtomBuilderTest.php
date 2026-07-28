<?php

namespace Yarunoka\Tests\Unit\Internal\Builder;

use Yarunoka\Schedule\CustomRef;
use Yarunoka\Schedule\LastDayOfMonth;
use Yarunoka\Schedule\MonthDay;
use Yarunoka\Schedule\OrdinalWeekday;
use Yarunoka\Schedule\Weekday;
use Yarunoka\Internal\Builder\DayAtomBuilder;
use Yarunoka\Internal\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\YrnkDayName;
use Yarunoka\Internal\Vocabulary\Ordinal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DayAtomBuilderTest extends TestCase
{
    #[Test]
    public function builds_every_atom_kind_into_its_raw_dsl_shape(): void
    {
        $this->assertSame(25, DayAtomBuilder::build(new MonthDay(25)));
        $this->assertSame('mon', DayAtomBuilder::build(new Weekday(YrnkDayName::Mon)));
        $this->assertSame('holiday', DayAtomBuilder::build(CalendarWord::Holiday));
        $this->assertSame(['3rd', 'mon'], DayAtomBuilder::build(new OrdinalWeekday(Ordinal::Third, YrnkDayName::Mon)));
        $this->assertSame('last_day_of_month', DayAtomBuilder::build(new LastDayOfMonth()));
        $this->assertSame('fête-nationale', DayAtomBuilder::build(new CustomRef('fête-nationale')));
    }
}
