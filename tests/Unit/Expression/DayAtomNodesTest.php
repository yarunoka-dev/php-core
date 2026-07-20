<?php

namespace Yarunoka\Tests\Unit\Expression;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Expression\CustomRef;
use Yarunoka\Expression\DayAtom;
use Yarunoka\Expression\DayExpression;
use Yarunoka\Expression\LastDayOfMonth;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\OrdinalWeekday;
use Yarunoka\Expression\Weekday;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Ordinal;
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
        $this->assertSame(DayName::Mon, (new Weekday(DayName::Mon))->dayName);
    }

    #[Test]
    public function ordinal_weekday_holds_the_ordinal_and_the_day_name(): void
    {
        $atom = new OrdinalWeekday(Ordinal::Third, DayName::Mon);

        $this->assertSame(Ordinal::Third, $atom->ordinal);
        $this->assertSame(DayName::Mon, $atom->dayName);
    }

    #[Test]
    public function custom_ref_holds_the_reference_name(): void
    {
        $this->assertSame('fête-nationale', (new CustomRef('fête-nationale'))->name);
    }

    #[Test]
    public function custom_ref_rejects_an_empty_name(): void
    {
        $this->expectException(InvalidValueException::class);

        new CustomRef('');
    }

    #[Test]
    public function custom_ref_rejects_a_whitespace_only_name(): void
    {
        $this->expectException(InvalidValueException::class);

        new CustomRef('   ');
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
            new OrdinalWeekday(Ordinal::Third, DayName::Mon),
            new OrdinalWeekday(Ordinal::Third, DayName::Mon),
        ]);
    }

    #[Test]
    public function every_atom_is_a_day_atom(): void
    {
        $this->assertInstanceOf(DayAtom::class, new MonthDay(25));
        $this->assertInstanceOf(DayAtom::class, new Weekday(DayName::Mon));
        $this->assertInstanceOf(DayAtom::class, new OrdinalWeekday(Ordinal::Last, DayName::Fri));
        $this->assertInstanceOf(DayAtom::class, new LastDayOfMonth());
        $this->assertInstanceOf(DayAtom::class, new CustomRef('founding-day'));
        $this->assertInstanceOf(DayAtom::class, CalendarWord::Holiday);
    }
}
