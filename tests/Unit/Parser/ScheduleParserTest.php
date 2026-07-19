<?php

namespace Yarunoka\Tests\Unit\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\AllDay;
use Yarunoka\Expression\BusinessHourRef;
use Yarunoka\Expression\CustomRef;
use Yarunoka\Expression\EveryGrid;
use Yarunoka\Expression\FixedTimes;
use Yarunoka\Expression\IfGuard;
use Yarunoka\Expression\LastDayOfMonth;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\OrdinalWeekday;
use Yarunoka\Expression\Shift;
use Yarunoka\Expression\Weekday;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Direction;
use Yarunoka\Vocabulary\Ordinal;
use Yarunoka\Vocabulary\TimeUnit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ScheduleParserTest extends TestCase
{
    private ScheduleParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ScheduleParser;
    }

    // ---- the day expression of days ----

    #[Test]
    public function the_days_enumeration_becomes_atom_nodes_per_kind(): void
    {
        $schedule = $this->parser->parse([
            'days' => [25, 'mon', 'holiday', ['3rd', 'mon'], 'last_day_of_month', 'founding-day'],
            'times' => ['10:00'],
        ]);

        $atoms = $schedule->days->atoms ?? [];

        $this->assertInstanceOf(MonthDay::class, $atoms[0]);
        $this->assertInstanceOf(Weekday::class, $atoms[1]);
        $this->assertSame(CalendarWord::Holiday, $atoms[2]);
        $this->assertInstanceOf(OrdinalWeekday::class, $atoms[3]);
        $this->assertSame(Ordinal::Third, $atoms[3]->ordinal);
        $this->assertInstanceOf(CustomRef::class, $atoms[5]);
    }

    #[Test]
    public function whether_a_custom_reference_exists_is_not_schedule_parsers_concern(): void
    {
        // Resolving references is the job of the holders of the
        // definitions (YrnkParser / YrnkEvaluator).
        $schedule = $this->parser->parse(['days' => ['name-defined-nowhere'], 'times' => ['10:00']]);

        $this->assertInstanceOf(CustomRef::class, $schedule->days?->atoms[0] ?? null);
    }

    #[Test]
    public function rejects_a_scalar_in_days(): void
    {
        // Scalar sugar was removed (2026-07-13). Always written as an
        // array.
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['days' => 'mon', 'times' => ['10:00']]);
    }

    #[Test]
    public function rejects_an_empty_days_enumeration(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['days' => [], 'times' => ['10:00']]);
    }

    #[Test]
    public function rejects_an_ordinal_word_outside_a_tuple(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['days' => ['3rd', 'mon'], 'times' => ['10:00']]);
    }

    #[Test]
    public function rejects_a_modifier_word_as_a_days_atom(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['days' => ['not', 'holiday'], 'times' => ['10:00']]);
    }

    #[Test]
    public function rejects_a_date_literal_as_a_days_atom(): void
    {
        // A specific date is given a name under a custom definition and
        // referred to.
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['days' => ['2026-10-01'], 'times' => ['10:00']]);
    }

    #[Test]
    public function rejects_a_reversed_ordinal_tuple(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['days' => [['mon', '3rd']], 'times' => ['10:00']]);
    }

    // ---- years / months ----

    #[Test]
    public function years_and_months_become_integer_enumerations(): void
    {
        $schedule = $this->parser->parse(['years' => [2043], 'months' => [2, 4], 'times' => ['10:00']]);

        $this->assertSame([2043], $schedule->years);
        $this->assertSame([2, 4], $schedule->months);
    }

    #[Test]
    public function rejects_a_scalar_in_months(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['months' => 2, 'times' => ['10:00']]);
    }

    #[Test]
    public function rejects_month_13(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['months' => [13], 'times' => ['10:00']]);
    }

    #[Test]
    public function rejects_string_years(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['years' => ['2043'], 'times' => ['10:00']]);
    }

    // ---- shift / if ----

    #[Test]
    public function shift_becomes_a_node(): void
    {
        $schedule = $this->parser->parse([
            'days' => [25],
            'shift' => ['prev', 'or_same', 'business_day'],
            'times' => ['10:00'],
        ]);

        $this->assertEquals(
            new Shift(Direction::Prev, orSame: true, condition: CalendarWord::BusinessDay),
            $schedule->shift,
        );
    }

    #[Test]
    public function shift_without_or_same_means_exclusive(): void
    {
        $schedule = $this->parser->parse([
            'days' => [25],
            'shift' => ['prev', 'business_day'],
            'times' => ['10:00'],
        ]);

        $this->assertEquals(
            new Shift(Direction::Prev, orSame: false, condition: CalendarWord::BusinessDay),
            $schedule->shift,
        );
    }

    #[Test]
    public function rejects_a_shift_without_a_direction(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['days' => [25], 'shift' => ['business_day'], 'times' => ['10:00']]);
    }

    #[Test]
    public function parses_the_four_forms_of_if(): void
    {
        $onlyCondition = $this->parser->parse(['days' => [13], 'if' => ['fri'], 'times' => ['10:00']]);
        $negated = $this->parser->parse(['days' => ['mon'], 'if' => ['not', 'holiday'], 'times' => ['10:00']]);
        $directed = $this->parser->parse(['if' => ['next', 'last_day_of_month'], 'times' => ['10:00']]);
        $both = $this->parser->parse(['if' => ['next', 'not', 'holiday'], 'times' => ['10:00']]);

        $this->assertEquals(
            new IfGuard(null, negated: false, condition: new Weekday(DayName::Fri)),
            $onlyCondition->if,
        );
        $this->assertEquals(
            new IfGuard(null, negated: true, condition: CalendarWord::Holiday),
            $negated->if,
        );
        $this->assertEquals(
            new IfGuard(Direction::Next, negated: false, condition: new LastDayOfMonth),
            $directed->if,
        );
        $this->assertEquals(
            new IfGuard(Direction::Next, negated: true, condition: CalendarWord::Holiday),
            $both->if,
        );
    }

    #[Test]
    public function rejects_same_as_a_direction(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['days' => ['mon'], 'if' => ['same', 'holiday'], 'times' => ['10:00']]);
    }

    // ---- times / allday ----

    #[Test]
    public function an_enumeration_of_fixed_times_becomes_fixed_times(): void
    {
        $schedule = $this->parser->parse(['times' => ['12:00', '09:00']]);

        $this->assertInstanceOf(FixedTimes::class, $schedule->times);
        $this->assertSame(12 * 3600, $schedule->times->times[0]->secondsFromMidnight);
    }

    #[Test]
    public function a_grid_becomes_an_every_grid(): void
    {
        $schedule = $this->parser->parse([
            'times' => ['every' => [90, 'minute'], 'between' => ['08:00', '20:00']],
        ]);

        $this->assertInstanceOf(EveryGrid::class, $schedule->times);
        $this->assertSame(90, $schedule->times->amount);
        $this->assertSame(TimeUnit::Minute, $schedule->times->unit);
        $this->assertInstanceOf(TimeWindow::class, $schedule->times->between);
    }

    #[Test]
    public function between_business_hour_becomes_a_reference_node(): void
    {
        $schedule = $this->parser->parse(['times' => ['every' => [1, 'hour'], 'between' => 'business_hour']]);

        $this->assertInstanceOf(EveryGrid::class, $schedule->times);
        $this->assertInstanceOf(BusinessHourRef::class, $schedule->times->between);
    }

    #[Test]
    public function rejects_any_other_name_in_between(): void
    {
        // User-defined window names were removed from the DSL.
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['times' => ['every' => [1, 'hour'], 'between' => 'afternoon']]);
    }

    #[Test]
    public function allday_becomes_all_day(): void
    {
        $schedule = $this->parser->parse(['allday' => true]);

        $this->assertInstanceOf(AllDay::class, $schedule->times);
    }

    #[Test]
    public function rejects_both_times_and_allday(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['times' => ['10:00'], 'allday' => true]);
    }

    #[Test]
    public function rejects_a_schedule_with_neither_times_nor_allday(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['days' => ['mon']]);
    }

    #[Test]
    public function rejects_allday_false(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['allday' => false]);
    }

    #[Test]
    public function rejects_a_time_without_zero_padding(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['times' => ['9:00']]);
    }

    #[Test]
    public function rejects_a_plural_unit_word(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['times' => ['every' => [2, 'hours']]]);
    }

    // ---- the structure of a schedule ----

    #[Test]
    public function rejects_an_unknown_key(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->parser->parse(['times' => ['10:00'], 'day' => ['mon']]);
    }
}
