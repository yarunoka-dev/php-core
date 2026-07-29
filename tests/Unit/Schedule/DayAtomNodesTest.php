<?php

namespace Yarunoka\Tests\Unit\Schedule;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Schedule\DateSetRef;
use Yarunoka\Schedule\DayAtomInterface;
use Yarunoka\Schedule\DayExpression;
use Yarunoka\Schedule\LastDayOfMonth;
use Yarunoka\Schedule\MonthDay;
use Yarunoka\Schedule\OrdinalWeekday;
use Yarunoka\Schedule\Weekday;
use Yarunoka\Internal\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\YrnkDayName;
use Yarunoka\Internal\Vocabulary\Ordinal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DayAtomNodesTest extends TestCase
{
    #[Test]
    public function month_day_holds_the_day_of_month(): void
    {
        $this->assertSame(1, (new MonthDay(1))->dayOfMonth);
        $this->assertSame(31, (new MonthDay(31))->dayOfMonth);
    }

    #[Test]
    public function month_day_rejects_zero(): void
    {
        $this->expectException(InvalidValueException::class);

        new MonthDay(0);
    }

    #[Test]
    public function month_day_rejects_32(): void
    {
        $this->expectException(InvalidValueException::class);

        new MonthDay(32);
    }

    #[Test]
    public function weekday_holds_the_day_name(): void
    {
        $this->assertSame(YrnkDayName::Mon, (new Weekday(YrnkDayName::Mon))->dayName);
    }

    #[Test]
    public function ordinal_weekday_holds_the_ordinal_and_the_day_name(): void
    {
        $atom = new OrdinalWeekday(Ordinal::Third, YrnkDayName::Mon);

        $this->assertSame(Ordinal::Third, $atom->ordinal);
        $this->assertSame(YrnkDayName::Mon, $atom->dayName);
    }

    #[Test]
    public function a_date_set_ref_holds_the_name(): void
    {
        $this->assertSame('fête-nationale', (new DateSetRef('fête-nationale'))->name);
    }

    #[Test]
    public function a_date_set_ref_rejects_an_empty_name(): void
    {
        $this->expectException(InvalidValueException::class);

        new DateSetRef('');
    }

    #[Test]
    public function a_date_set_ref_rejects_a_whitespace_only_name(): void
    {
        $this->expectException(InvalidValueException::class);

        new DateSetRef('   ');
    }

    #[Test]
    public function day_expression_rejects_duplicate_atoms(): void
    {
        $this->expectException(InvalidValueException::class);

        new DayExpression([new MonthDay(25), new MonthDay(25)]);
    }

    #[Test]
    public function day_expression_rejects_duplicate_ordinal_tuples(): void
    {
        $this->expectException(InvalidValueException::class);

        new DayExpression([
            new OrdinalWeekday(Ordinal::Third, YrnkDayName::Mon),
            new OrdinalWeekday(Ordinal::Third, YrnkDayName::Mon),
        ]);
    }

    #[Test]
    public function every_atom_is_a_day_atom(): void
    {
        $this->assertInstanceOf(DayAtomInterface::class, new MonthDay(25));
        $this->assertInstanceOf(DayAtomInterface::class, new Weekday(YrnkDayName::Mon));
        $this->assertInstanceOf(DayAtomInterface::class, new OrdinalWeekday(Ordinal::Last, YrnkDayName::Fri));
        $this->assertInstanceOf(DayAtomInterface::class, new LastDayOfMonth());
        $this->assertInstanceOf(DayAtomInterface::class, new DateSetRef('founding-day'));
        $this->assertInstanceOf(DayAtomInterface::class, CalendarWord::Holiday);
    }
}
