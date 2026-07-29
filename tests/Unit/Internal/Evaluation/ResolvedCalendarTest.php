<?php

namespace Yarunoka\Tests\Unit\Internal\Evaluation;

use Yarunoka\Calendar\YrnkBusinessDays;
use Yarunoka\Calendar\YrnkBusinessHolidays;
use Yarunoka\Calendar\YrnkBusinessHours;
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkDateSet;
use Yarunoka\Calendar\YrnkHolidays;
use Yarunoka\Calendar\YrnkWorkweek;
use Yarunoka\Exceptions\InvalidCalendarDataException;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Exceptions\UnregisteredResolverException;
use Yarunoka\Internal\Evaluation\ResolvedCalendar;
use Yarunoka\Tests\Support\CountingResolver;
use Yarunoka\YrnkDate;
use Yarunoka\Time\YrnkTimeWindow;
use Yarunoka\Vocabulary\YrnkDayName;
use DateTimeZone;
use Yarunoka\Tests\Support\Bindings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResolvedCalendarTest extends TestCase
{
    #[Test]
    public function contains_per_layer_is_true_only_for_the_defined_dates(): void
    {
        $resolved = new ResolvedCalendar(new YrnkCalendar(
            holidays: YrnkHolidays::ofDates(['2026-01-01'], self::utc()),
            businessHolidays: YrnkBusinessHolidays::ofDates(['2026-08-13'], self::utc()),
            businessDays: YrnkBusinessDays::ofDates(['2026-07-11'], self::utc()),
        ), timezone: self::utc());

        $this->assertTrue($resolved->holidayContains(new YrnkDate('2026-01-01', self::utc())));
        $this->assertFalse($resolved->holidayContains(new YrnkDate('2026-01-02', self::utc())));
        $this->assertTrue($resolved->businessHolidayContains(new YrnkDate('2026-08-13', self::utc())));
        $this->assertTrue($resolved->businessDayContains(new YrnkDate('2026-07-11', self::utc())));
    }

    #[Test]
    public function contains_for_custom_looks_up_the_set_per_name(): void
    {
        $resolved = new ResolvedCalendar(new YrnkCalendar(
            dateSets: ['founding-day' => YrnkDateSet::ofDates(['2026-10-01'], self::utc())],
        ), timezone: self::utc());

        $this->assertTrue($resolved->nameContains('founding-day', new YrnkDate('2026-10-01', self::utc())));
        $this->assertFalse($resolved->nameContains('founding-day', new YrnkDate('2026-10-02', self::utc())));
    }

    #[Test]
    public function an_undefined_custom_name_raises(): void
    {
        $resolved = new ResolvedCalendar(new YrnkCalendar(), timezone: self::utc());

        $this->expectException(UndefinedNameException::class);

        $resolved->nameContains('nowhere-to-be-found', new YrnkDate('2026-10-01', self::utc()));
    }

    #[Test]
    public function the_workweek_default_is_monday_through_friday(): void
    {
        $resolved = new ResolvedCalendar(new YrnkCalendar(), timezone: self::utc());

        $this->assertTrue($resolved->isInWorkweek(YrnkDayName::Mon));
        $this->assertTrue($resolved->isInWorkweek(YrnkDayName::Fri));
        $this->assertFalse($resolved->isInWorkweek(YrnkDayName::Sat));
    }

    #[Test]
    public function the_workweek_can_be_replaced(): void
    {
        $resolved = new ResolvedCalendar(new YrnkCalendar(
            workweek: new YrnkWorkweek([YrnkDayName::Tue, YrnkDayName::Sat]),
        ), timezone: self::utc());

        $this->assertTrue($resolved->isInWorkweek(YrnkDayName::Sat));
        $this->assertFalse($resolved->isInWorkweek(YrnkDayName::Mon));
    }

    #[Test]
    public function returns_the_business_hours_windows_and_raises_when_undefined(): void
    {
        $withWindows = new ResolvedCalendar(new YrnkCalendar(
            businessHours: new YrnkBusinessHours([YrnkTimeWindow::fromStrings('09:00', '18:00')]),
        ), timezone: self::utc());
        $without = new ResolvedCalendar(new YrnkCalendar(), timezone: self::utc());

        $this->assertCount(1, $withWindows->businessHourWindows());

        $this->expectException(MissingCalendarDataException::class);

        $without->businessHourWindows();
    }

    #[Test]
    public function a_resolver_resolves_on_first_reference_and_is_called_at_most_once(): void
    {
        $resolver = new CountingResolver(['2026-01-01']);
        $resolved = new ResolvedCalendar(
            new YrnkCalendar(
                holidays: 'counting',
                resolverContainer: Bindings::of(['counting' => $resolver]),
            ),
            timezone: self::utc(),
        );

        $this->assertSame(0, $resolver->calls);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
        $resolved->holidayContains(new YrnkDate('2026-05-05', self::utc()));

        $this->assertSame(1, $resolver->calls);
    }

    #[Test]
    public function an_unregistered_resolver_name_raises(): void
    {
        $resolved = new ResolvedCalendar(
            new YrnkCalendar(holidays: 'unknown'),
            timezone: self::utc(),
        );

        $this->expectException(UnregisteredResolverException::class);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
    }

    #[Test]
    public function a_contract_violation_in_the_resolver_return_value_raises(): void
    {
        $resolved = new ResolvedCalendar(
            new YrnkCalendar(
                holidays: 'broken',
                resolverContainer: Bindings::of(['broken' => Bindings::returning(['2026/01/01'])]),
            ),
            timezone: self::utc(),
        );

        $this->expectException(InvalidCalendarDataException::class);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
    }

    #[Test]
    public function referencing_an_undefined_layer_raises_the_safeguard_error(): void
    {
        $resolved = new ResolvedCalendar(new YrnkCalendar(), timezone: self::utc());

        $this->expectException(MissingCalendarDataException::class);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
    }

    #[Test]
    public function a_resolver_supplies_the_dates_of_the_name_it_is_bound_to(): void
    {
        $resolved = new ResolvedCalendar(
            new YrnkCalendar(
                holidays: 'jp',
                resolverContainer: Bindings::of(['jp' => new CountingResolver(['2026-01-01'])]),
            ),
            timezone: self::utc(),
        );

        $this->assertTrue($resolved->holidayContains(new YrnkDate('2026-01-01', self::utc())));
        $this->assertFalse($resolved->holidayContains(new YrnkDate('2026-01-02', self::utc())));
    }



    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
