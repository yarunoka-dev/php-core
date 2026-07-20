<?php

namespace Yarunoka\Tests\Unit\Internal\Evaluation;

use Yarunoka\Definitions\BusinessDays;
use Yarunoka\Definitions\BusinessHolidays;
use Yarunoka\Definitions\CustomDefinition;
use Yarunoka\Definitions\Definitions;
use Yarunoka\Definitions\Holidays;
use Yarunoka\Definitions\Workweek;
use Yarunoka\Expression\CustomRef;
use Yarunoka\Expression\LastDayOfMonth;
use Yarunoka\Expression\MonthDay;
use Yarunoka\Expression\OrdinalWeekday;
use Yarunoka\Expression\Weekday;
use Yarunoka\Internal\Evaluation\AtomDayEnumerator;
use Yarunoka\Internal\Evaluation\DayMatcher;
use Yarunoka\Internal\Evaluation\ResolvedDefinitions;
use Yarunoka\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Ordinal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Atom × (year, month) → the enumeration of matching days of that month.
 * The date facts follow the actual 2026 calendar (the Mondays of 2026-07
 * are 6, 13, 20, 27).
 */
class AtomDayEnumeratorTest extends TestCase
{
    // ---- day of month ----

    #[Test]
    public function a_day_of_month_enumerates_as_one_day_when_the_month_has_it(): void
    {
        $enumerator = $this->enumerator();

        $this->assertSame([25], $enumerator->daysIn(new MonthDay(25), 2026, 7));
    }

    #[Test]
    public function a_day_of_month_the_month_does_not_have_enumerates_empty(): void
    {
        $enumerator = $this->enumerator();

        $this->assertSame([], $enumerator->daysIn(new MonthDay(30), 2026, 2));
        $this->assertSame([], $enumerator->daysIn(new MonthDay(31), 2026, 4));
        $this->assertSame([29], $enumerator->daysIn(new MonthDay(29), 2024, 2)); // a leap year has the 29th
    }

    // ---- day of week ----

    #[Test]
    public function a_weekday_enumerates_every_matching_day_of_the_month(): void
    {
        $enumerator = $this->enumerator();

        $this->assertSame([6, 13, 20, 27], $enumerator->daysIn(new Weekday(DayName::Mon), 2026, 7));
        $this->assertSame([1, 8, 15, 22, 29], $enumerator->daysIn(new Weekday(DayName::Wed), 2026, 7));
    }

    #[Test]
    public function the_weekday_of_the_first_of_the_month_enumerates_correctly_too(): void
    {
        // 2026-06-01 is a Monday.
        $enumerator = $this->enumerator();

        $this->assertSame([1, 8, 15, 22, 29], $enumerator->daysIn(new Weekday(DayName::Mon), 2026, 6));
    }

    // ---- nth weekday ----

    #[Test]
    public function an_ordinal_tuple_enumerates_the_single_nth_weekday(): void
    {
        $enumerator = $this->enumerator();

        $this->assertSame([20], $enumerator->daysIn(new OrdinalWeekday(Ordinal::Third, DayName::Mon), 2026, 7));
        $this->assertSame([31], $enumerator->daysIn(new OrdinalWeekday(Ordinal::Last, DayName::Fri), 2026, 7));
    }

    #[Test]
    public function an_ordinal_tuple_enumerates_empty_in_a_month_without_the_fifth_week(): void
    {
        // 2026-07 has four Mondays.
        $enumerator = $this->enumerator();

        $this->assertSame([], $enumerator->daysIn(new OrdinalWeekday(Ordinal::Fifth, DayName::Mon), 2026, 7));
        $this->assertSame([31], $enumerator->daysIn(new OrdinalWeekday(Ordinal::Fifth, DayName::Fri), 2026, 7));
    }

    // ---- end of month ----

    #[Test]
    public function the_end_of_month_follows_the_number_of_days_in_the_month(): void
    {
        $enumerator = $this->enumerator();

        $this->assertSame([31], $enumerator->daysIn(new LastDayOfMonth(), 2026, 7));
        $this->assertSame([28], $enumerator->daysIn(new LastDayOfMonth(), 2026, 2));
        $this->assertSame([29], $enumerator->daysIn(new LastDayOfMonth(), 2024, 2));
    }

    // ---- custom references ----

    #[Test]
    public function a_custom_reference_enumerates_only_that_month_in_ascending_order(): void
    {
        $enumerator = $this->enumerator(custom: [
            'anniversary' => ['2026-07-20', '2026-07-05', '2026-08-01', '2025-07-10'],
        ]);

        $this->assertSame([5, 20], $enumerator->daysIn(new CustomRef('anniversary'), 2026, 7));
        $this->assertSame([], $enumerator->daysIn(new CustomRef('anniversary'), 2026, 9));
    }

    // ---- calendar vocabulary ----

    #[Test]
    public function weekday_and_weekend_enumerate_the_calendar_weekdays_and_weekends(): void
    {
        $enumerator = $this->enumerator();

        $this->assertSame(
            [1, 2, 3, 6, 7, 8, 9, 10, 13, 14, 15, 16, 17, 20, 21, 22, 23, 24, 27, 28, 29, 30, 31],
            $enumerator->daysIn(CalendarWord::Weekday, 2026, 7),
        );
        $this->assertSame([4, 5, 11, 12, 18, 19, 25, 26], $enumerator->daysIn(CalendarWord::Weekend, 2026, 7));
    }

    #[Test]
    public function holiday_enumerates_that_months_part_of_the_holidays_list(): void
    {
        $enumerator = $this->enumerator(holidays: ['2026-07-20', '2026-08-11', '2026-07-01']);

        $this->assertSame([1, 20], $enumerator->daysIn(CalendarWord::Holiday, 2026, 7));
    }

    #[Test]
    public function business_day_enumerates_the_conclusion_of_the_layer_model(): void
    {
        // 7/20 (Mon, a holiday) falls to a day off, and 7/11 (Sat) returns
        // to working via business_days.
        $enumerator = $this->enumerator(holidays: ['2026-07-20'], businessDays: ['2026-07-11']);

        $this->assertSame(
            [1, 2, 3, 6, 7, 8, 9, 10, 11, 13, 14, 15, 16, 17, 21, 22, 23, 24, 27, 28, 29, 30, 31],
            $enumerator->daysIn(CalendarWord::BusinessDay, 2026, 7),
        );
    }

    #[Test]
    public function business_holiday_is_the_complement_of_business_day(): void
    {
        $enumerator = $this->enumerator(holidays: ['2026-07-20']);

        $this->assertSame(
            [4, 5, 11, 12, 18, 19, 20, 25, 26],
            $enumerator->daysIn(CalendarWord::BusinessHoliday, 2026, 7),
        );
    }

    #[Test]
    public function replacing_the_weekly_pattern_changes_the_business_day_enumeration(): void
    {
        $enumerator = $this->enumerator(workweek: ['sat', 'sun']);

        $this->assertSame([4, 5, 11, 12, 18, 19, 25, 26], $enumerator->daysIn(CalendarWord::BusinessDay, 2026, 7));
    }

    // ---- helpers ----

    /**
     * @param  list<string>  $holidays
     * @param  list<string>  $businessDays
     * @param  list<string>|null  $workweek
     * @param  array<string, list<string>>  $custom
     */
    private function enumerator(
        array $holidays = [],
        array $businessDays = [],
        ?array $workweek = null,
        array $custom = [],
    ): AtomDayEnumerator {
        return new AtomDayEnumerator(new DayMatcher(new ResolvedDefinitions(new Definitions(
            holidays: Holidays::ofDates($holidays),
            businessHolidays: BusinessHolidays::ofDates([]),
            businessDays: BusinessDays::ofDates($businessDays),
            workweek: $workweek === null ? null : new Workweek(
                array_map(static fn(string $day): DayName => DayName::from($day), $workweek),
            ),
            custom: array_map(
                static fn(array $dates): CustomDefinition => CustomDefinition::ofDates($dates),
                $custom,
            ),
        ), resolvers: [])));
    }
}
