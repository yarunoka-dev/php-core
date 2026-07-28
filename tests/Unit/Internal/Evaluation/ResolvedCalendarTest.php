<?php

namespace Yarunoka\Tests\Unit\Internal\Evaluation;

use Yarunoka\Calendar\BusinessDays;
use Yarunoka\Calendar\BusinessHolidays;
use Yarunoka\Calendar\BusinessHours;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Calendar\CustomDefinition;
use Yarunoka\Calendar\Holidays;
use Yarunoka\Calendar\Workweek;
use Yarunoka\Exceptions\InvalidCalendarDataException;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Internal\Evaluation\ResolvedCalendar;
use Yarunoka\Tests\Support\CountingResolver;
use Yarunoka\YrnkDate;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\DayName;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResolvedCalendarTest extends TestCase
{
    #[Test]
    public function contains_per_layer_is_true_only_for_the_defined_dates(): void
    {
        $resolved = new ResolvedCalendar(new Calendar(
            holidays: Holidays::ofDates(['2026-01-01'], self::utc()),
            businessHolidays: BusinessHolidays::ofDates(['2026-08-13'], self::utc()),
            businessDays: BusinessDays::ofDates(['2026-07-11'], self::utc()),
        ), resolvers: [], timezone: self::utc());

        $this->assertTrue($resolved->holidayContains(new YrnkDate('2026-01-01', self::utc())));
        $this->assertFalse($resolved->holidayContains(new YrnkDate('2026-01-02', self::utc())));
        $this->assertTrue($resolved->businessHolidayContains(new YrnkDate('2026-08-13', self::utc())));
        $this->assertTrue($resolved->businessDayContains(new YrnkDate('2026-07-11', self::utc())));
    }

    #[Test]
    public function contains_for_custom_looks_up_the_set_per_name(): void
    {
        $resolved = new ResolvedCalendar(new Calendar(
            custom: ['founding-day' => CustomDefinition::ofDates(['2026-10-01'], self::utc())],
        ), resolvers: [], timezone: self::utc());

        $this->assertTrue($resolved->customContains('founding-day', new YrnkDate('2026-10-01', self::utc())));
        $this->assertFalse($resolved->customContains('founding-day', new YrnkDate('2026-10-02', self::utc())));
    }

    #[Test]
    public function an_undefined_custom_name_raises(): void
    {
        $resolved = new ResolvedCalendar(new Calendar(), resolvers: [], timezone: self::utc());

        $this->expectException(UndefinedNameException::class);

        $resolved->customContains('nowhere-to-be-found', new YrnkDate('2026-10-01', self::utc()));
    }

    #[Test]
    public function the_workweek_default_is_monday_through_friday(): void
    {
        $resolved = new ResolvedCalendar(new Calendar(), resolvers: [], timezone: self::utc());

        $this->assertTrue($resolved->isInWorkweek(DayName::Mon));
        $this->assertTrue($resolved->isInWorkweek(DayName::Fri));
        $this->assertFalse($resolved->isInWorkweek(DayName::Sat));
    }

    #[Test]
    public function the_workweek_can_be_replaced(): void
    {
        $resolved = new ResolvedCalendar(new Calendar(
            workweek: new Workweek([DayName::Tue, DayName::Sat]),
        ), resolvers: [], timezone: self::utc());

        $this->assertTrue($resolved->isInWorkweek(DayName::Sat));
        $this->assertFalse($resolved->isInWorkweek(DayName::Mon));
    }

    #[Test]
    public function returns_the_business_hours_windows_and_raises_when_undefined(): void
    {
        $withWindows = new ResolvedCalendar(new Calendar(
            businessHours: new BusinessHours([TimeWindow::fromStrings('09:00', '18:00')]),
        ), resolvers: [], timezone: self::utc());
        $without = new ResolvedCalendar(new Calendar(), resolvers: [], timezone: self::utc());

        $this->assertCount(1, $withWindows->businessHourWindows());

        $this->expectException(MissingCalendarDataException::class);

        $without->businessHourWindows();
    }

    #[Test]
    public function a_resolver_resolves_on_first_reference_and_is_called_at_most_once(): void
    {
        $calls = 0;
        $resolved = new ResolvedCalendar(
            new Calendar(holidays: Holidays::byResolver('counting')),
            resolvers: ['counting' => function () use (&$calls): array {
                $calls++;

                return ['2026-01-01'];
            }],
            timezone: self::utc(),
        );

        $this->assertSame(0, $calls);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
        $resolved->holidayContains(new YrnkDate('2026-05-05', self::utc()));

        $this->assertSame(1, $calls);
    }

    #[Test]
    public function an_unregistered_resolver_name_raises(): void
    {
        $resolved = new ResolvedCalendar(
            new Calendar(holidays: Holidays::byResolver('unknown')),
            resolvers: [],
            timezone: self::utc(),
        );

        $this->expectException(UndefinedNameException::class);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
    }

    #[Test]
    public function a_contract_violation_in_the_resolver_return_value_raises(): void
    {
        $resolved = new ResolvedCalendar(
            new Calendar(holidays: Holidays::byResolver('broken')),
            resolvers: ['broken' => static fn(): array => ['2026/01/01']],
            timezone: self::utc(),
        );

        $this->expectException(InvalidCalendarDataException::class);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
    }

    #[Test]
    public function referencing_an_undefined_layer_raises_the_safeguard_error(): void
    {
        $resolved = new ResolvedCalendar(new Calendar(), resolvers: [], timezone: self::utc());

        $this->expectException(MissingCalendarDataException::class);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
    }

    #[Test]
    public function a_resolver_contract_instance_can_be_a_source_too(): void
    {
        $resolved = new ResolvedCalendar(
            new Calendar(holidays: Holidays::byResolver('jp')),
            resolvers: ['jp' => new CountingResolver(['2026-01-01'])],
            timezone: self::utc(),
        );

        $this->assertTrue($resolved->holidayContains(new YrnkDate('2026-01-01', self::utc())));
        $this->assertFalse($resolved->holidayContains(new YrnkDate('2026-01-02', self::utc())));
    }

    #[Test]
    public function a_resolver_contract_instance_is_called_at_most_once_too(): void
    {
        $resolver = new CountingResolver(['2026-01-01']);
        $resolved = new ResolvedCalendar(
            new Calendar(holidays: Holidays::byResolver('jp')),
            resolvers: ['jp' => $resolver],
            timezone: self::utc(),
        );

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
        $resolved->holidayContains(new YrnkDate('2026-01-02', self::utc()));

        $this->assertSame(1, $resolver->calls);
    }

    #[Test]
    public function the_return_value_of_a_resolver_contract_instance_is_validated_too(): void
    {
        $resolved = new ResolvedCalendar(
            new Calendar(holidays: Holidays::byResolver('broken')),
            resolvers: ['broken' => new CountingResolver(['2026/01/01'])],
            timezone: self::utc(),
        );

        $this->expectException(InvalidCalendarDataException::class);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
