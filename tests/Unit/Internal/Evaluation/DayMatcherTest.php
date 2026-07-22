<?php

namespace Yarunoka\Tests\Unit\Internal\Evaluation;

use Yarunoka\Calendar\BusinessDays;
use Yarunoka\Calendar\BusinessHolidays;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Calendar\CustomDefinition;
use Yarunoka\Calendar\Holidays;
use Yarunoka\Expression\CustomRef;
use Yarunoka\Expression\LastDayOfMonth;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\OrdinalWeekday;
use Yarunoka\Expression\Weekday;
use Yarunoka\Internal\Evaluation\DayMatcher;
use Yarunoka\Internal\Evaluation\ResolvedCalendar;
use Yarunoka\Time\LocalDate;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Ordinal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DayMatcherTest extends TestCase
{
    #[Test]
    public function matches_the_calendar_arithmetic_atoms(): void
    {
        $matcher = $this->matcher();

        // 2026-07-20 is the third Monday. 7/31 is the last Friday and the
        // end of the month.
        $this->assertTrue($matcher->matches(new MonthDay(20), $this->day('2026-07-20')));
        $this->assertTrue($matcher->matches(new Weekday(DayName::Mon), $this->day('2026-07-20')));
        $this->assertTrue($matcher->matches(new OrdinalWeekday(Ordinal::Third, DayName::Mon), $this->day('2026-07-20')));
        $this->assertFalse($matcher->matches(new OrdinalWeekday(Ordinal::Fifth, DayName::Mon), $this->day('2026-07-27')));
        $this->assertTrue($matcher->matches(new OrdinalWeekday(Ordinal::Last, DayName::Fri), $this->day('2026-07-31')));
        $this->assertTrue($matcher->matches(new LastDayOfMonth(), $this->day('2026-07-31')));
        $this->assertFalse($matcher->matches(new LastDayOfMonth(), $this->day('2026-07-30')));
    }

    #[Test]
    public function a_custom_reference_looks_up_the_defined_set(): void
    {
        $matcher = $this->matcher(custom: ['founding-day' => ['2026-10-01']]);

        $this->assertTrue($matcher->matches(new CustomRef('founding-day'), $this->day('2026-10-01')));
        $this->assertFalse($matcher->matches(new CustomRef('founding-day'), $this->day('2026-10-02')));
    }

    #[Test]
    public function weekday_and_weekend_are_calendar_fixed_and_unaffected_by_definitions(): void
    {
        // 2026-07-11 is a Saturday. Putting it into business_days keeps it
        // a weekend.
        $matcher = $this->matcher(businessDays: ['2026-07-11']);

        $this->assertTrue($matcher->matches(CalendarWord::Weekend, $this->day('2026-07-11')));
        $this->assertFalse($matcher->matches(CalendarWord::Weekday, $this->day('2026-07-11')));
    }

    #[Test]
    public function holiday_asks_the_holidays_list_alone(): void
    {
        $matcher = $this->matcher(holidays: ['2026-01-01'], businessDays: ['2026-01-01']);

        $this->assertTrue($matcher->matches(CalendarWord::Holiday, $this->day('2026-01-01')));
        $this->assertTrue($matcher->matches(CalendarWord::BusinessDay, $this->day('2026-01-01')));
    }

    #[Test]
    public function business_day_is_decided_by_the_layer_priority(): void
    {
        $matcher = $this->matcher(
            holidays: ['2026-07-11'],
            businessHolidays: ['2026-07-11', '2026-08-13'],
            businessDays: ['2026-07-11'],
        );

        // business_days is the top layer and overrides everything.
        $this->assertTrue($matcher->matches(CalendarWord::BusinessDay, $this->day('2026-07-11')));
        // The organization's own closure turns a weekday (Thu 2026-08-13)
        // into a day off.
        $this->assertFalse($matcher->matches(CalendarWord::BusinessDay, $this->day('2026-08-13')));
        // A Saturday in none of the lists is off by the weekly pattern.
        $this->assertTrue($matcher->matches(CalendarWord::BusinessHoliday, $this->day('2026-07-18')));
        // A Monday in none of the lists is working by the weekly pattern.
        $this->assertTrue($matcher->matches(CalendarWord::BusinessDay, $this->day('2026-07-13')));
    }

    // ---- helpers ----

    /**
     * @param  list<string>  $holidays
     * @param  list<string>  $businessHolidays
     * @param  list<string>  $businessDays
     * @param  array<string, list<string>>  $custom
     */
    private function matcher(
        array $holidays = [],
        array $businessHolidays = [],
        array $businessDays = [],
        array $custom = [],
    ): DayMatcher {
        return new DayMatcher(new ResolvedCalendar(new Calendar(
            holidays: Holidays::ofDates($holidays),
            businessHolidays: BusinessHolidays::ofDates($businessHolidays),
            businessDays: BusinessDays::ofDates($businessDays),
            custom: array_map(
                static fn(array $dates): CustomDefinition => CustomDefinition::ofDates($dates),
                $custom,
            ),
        ), resolvers: []));
    }

    private function day(string $date): LocalDate
    {
        return LocalDate::fromString($date);
    }
}
