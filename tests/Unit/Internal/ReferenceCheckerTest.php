<?php

namespace Yarunoka\Tests\Unit\Internal;

use Yarunoka\Calendar\YrnkBusinessDays;
use Yarunoka\Calendar\YrnkBusinessHours;
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkDateSet;
use Yarunoka\Calendar\YrnkHolidays;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Exceptions\UnregisteredResolverException;
use Yarunoka\Internal\ReferenceChecker;
use Yarunoka\Schedule\YrnkScheduleParser;
use Yarunoka\Time\YrnkTimeWindow;
use Yarunoka\YrnkSchedule;
use DateTimeZone;
use Yarunoka\Tests\Support\Bindings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReferenceCheckerTest extends TestCase
{
    #[Test]
    public function resolvable_references_do_not_raise(): void
    {
        ReferenceChecker::ensureResolvable(
            [$this->schedule(['days' => ['holiday', 'founding-day'], 'times' => ['09:00']])],
            new YrnkCalendar(
                holidays: 'yasumi-jp',
                dateSets: ['founding-day' => YrnkDateSet::ofDates(['2026-10-01'], self::utc())],
                resolverContainer: Bindings::of(['yasumi-jp' => Bindings::returning([])]),
            ),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function an_undefined_custom_reference_raises(): void
    {
        $this->expectException(UndefinedNameException::class);

        ReferenceChecker::ensureResolvable(
            [$this->schedule(['days' => ['founding-day'], 'times' => ['09:00']])],
            new YrnkCalendar(),
        );
    }

    #[Test]
    public function holiday_requires_the_holidays_definition(): void
    {
        $this->expectException(MissingCalendarDataException::class);

        ReferenceChecker::ensureResolvable(
            [$this->schedule(['days' => ['holiday'], 'times' => ['09:00']])],
            new YrnkCalendar(),
        );
    }

    #[Test]
    public function business_day_requires_all_three_layers_and_lists_the_missing_ones(): void
    {
        try {
            ReferenceChecker::ensureResolvable(
                [$this->schedule(['days' => ['business_day'], 'times' => ['09:00']])],
                new YrnkCalendar(holidays: YrnkHolidays::ofDates([], self::utc())),
            );
            $this->fail('MissingCalendarDataException was not thrown');
        } catch (MissingCalendarDataException $e) {
            $this->assertStringContainsString('business_holidays', $e->getMessage());
            $this->assertStringContainsString('business_days', $e->getMessage());
        }
    }

    #[Test]
    public function the_vocabulary_in_shift_and_if_conditions_is_checked_too(): void
    {
        $this->expectException(MissingCalendarDataException::class);

        ReferenceChecker::ensureResolvable(
            [$this->schedule(['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['09:00']])],
            new YrnkCalendar(),
        );
    }

    #[Test]
    public function a_business_hour_reference_requires_the_business_hours_definition(): void
    {
        $this->expectException(MissingCalendarDataException::class);

        ReferenceChecker::ensureResolvable(
            [$this->schedule(['times' => ['every' => [1, 'hour'], 'between' => 'business_hour']])],
            new YrnkCalendar(),
        );
    }

    #[Test]
    public function a_business_hour_reference_passes_when_business_hours_is_defined(): void
    {
        ReferenceChecker::ensureResolvable(
            [$this->schedule(['times' => ['every' => [1, 'hour'], 'between' => 'business_hour']])],
            new YrnkCalendar(businessHours: new YrnkBusinessHours([YrnkTimeWindow::fromStrings('09:00', '18:00')])),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function an_unregistered_resolver_name_raises(): void
    {
        $this->expectException(UnregisteredResolverException::class);

        ReferenceChecker::ensureResolvable(
            [$this->schedule(['days' => ['weekday'], 'times' => ['09:00']])],
            new YrnkCalendar(businessDays: 'unknown'),
        );
    }

    #[Test]
    public function a_date_list_position_resolves_against_the_date_sets_too(): void
    {
        // One namespace: the name is an entry of date_sets here, and no
        // binding is needed for it.
        ReferenceChecker::ensureResolvable(
            [$this->schedule(['days' => ['holiday'], 'times' => ['09:00']])],
            new YrnkCalendar(
                holidays: 'founding-day',
                dateSets: ['founding-day' => YrnkDateSet::ofDates(['2026-10-01'], self::utc())],
            ),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function a_name_in_days_resolves_against_the_bindings_too(): void
    {
        ReferenceChecker::ensureResolvable(
            [$this->schedule(['days' => ['garbage-days'], 'times' => ['09:00']])],
            new YrnkCalendar(
                resolverContainer: Bindings::of(['garbage-days' => Bindings::returning([])]),
            ),
        );

        $this->expectNotToPerformAssertions();
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function schedule(array $raw): YrnkSchedule
    {
        return (new YrnkScheduleParser())->parse($raw, self::utc());
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
