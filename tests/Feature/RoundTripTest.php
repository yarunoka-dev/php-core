<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Schedule\YrnkScheduleBuilder;
use Yarunoka\YrnkBuilder;
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkHolidaysDateSet;
use Yarunoka\Schedule\AllDay;
use Yarunoka\Schedule\YrnkScheduleParser;
use Yarunoka\YrnkParser;
use Yarunoka\Yrnk;
use Yarunoka\YrnkSchedule;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use Yarunoka\Tests\Support\Bindings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Round-tripping is the identity: build(parse($dsl)) = $dsl (as the array
 * representation). Instances do not normalize the input notation — order
 * and units stay as written, which is guaranteed here.
 */
class RoundTripTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $raw
     */
    #[Test]
    #[DataProvider('documents')]
    public function a_document_round_trip_is_the_identity(array $raw): void
    {
        $parser = new YrnkParser(Bindings::of([
            'yasumi-jp' => Bindings::returning(['2026-01-01']),
            'garbage-days' => Bindings::returning([]),
        ]));

        $this->assertSame($raw, (new YrnkBuilder())->build($parser->parse($raw)));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    #[Test]
    #[DataProvider('schedules')]
    public function a_single_schedule_round_trip_is_the_identity(array $raw): void
    {
        $this->assertSame($raw, (new YrnkScheduleBuilder())->build((new YrnkScheduleParser())->parse($raw, self::tz())));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function documents(): array
    {
        return [
            'a full calendar with a payday and an anniversary' => [[
                'version' => '1.0',
                'timezone' => 'Asia/Tokyo',
                'calendar' => [
                    'holidays' => ['2026-01-01', '2026-01-12'],
                    'business_holidays' => [],
                    'business_days' => [],
                    'workweek' => ['tue', 'wed', 'thu', 'fri', 'sat'],
                    'business_hours' => [['09:00', '12:00'], ['13:00', '18:00']],
                    'date_sets' => ['founding-day' => ['2026-10-01']],
                ],
                'schedules' => [
                    ['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']],
                    ['days' => ['founding-day'], 'allday' => true],
                ],
            ]],
            'resolver name references' => [[
                'version' => '1.0',
                'timezone' => 'Asia/Tokyo',
                'resolvers' => ['yasumi-jp', 'garbage-days'],
                'calendar' => [
                    'holidays' => 'yasumi-jp',
                    'business_holidays' => 'garbage-days',
                    'date_sets' => ['founding-day' => ['2026-10-01']],
                ],
                'schedules' => [
                    ['days' => ['holiday'], 'times' => ['08:00']],
                    ['days' => ['garbage-days', 'founding-day'], 'allday' => true],
                ],
            ]],
            'notation preservation (times order and every unit)' => [[
                'version' => '1.0',
                'timezone' => 'UTC',
                'schedules' => [
                    ['times' => ['12:00', '09:00']],
                    ['days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                        'times' => ['every' => [90, 'minute'], 'between' => ['08:30', '24:00']]],
                ],
            ]],
            'boundaries inside a spring-forward gap stay as written' => [[
                'version' => '1.0',
                'timezone' => 'Europe/Berlin',
                'schedules' => [
                    // 02:30 and 02:45 do not exist on 2021-03-28 (the clock
                    // jumps 02:00 -> 03:00). Evaluation resolves them
                    // forward, but the document keeps the authored spelling.
                    ['from' => '2021-03-28 02:30', 'until' => '2021-03-28 02:45',
                        'years' => [2021], 'months' => [3], 'days' => [28], 'times' => ['03:30']],
                ],
            ]],
            'a business_hour reference and if' => [[
                'version' => '1.0',
                // A backward link is a tz database entry and must round-trip
                // as written (never canonicalized to Asia/Tokyo).
                'timezone' => 'Japan',
                'calendar' => [
                    'business_hours' => [['09:00', '18:00']],
                ],
                'schedules' => [
                    ['days' => [['1st', 'fri'], ['3rd', 'fri']], 'if' => ['next', 'not', 'last_day_of_month'],
                        'times' => ['every' => [1, 'hour'], 'between' => 'business_hour']],
                ],
            ]],
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function schedules(): array
    {
        return [
            'all fields' => [[
                'from' => '2026-01-01 00:00',
                'until' => '2044-12-31 23:59',
                'years' => [2043, 2044],
                'months' => [6],
                'days' => [15, 'sun', ['last', 'fri'], 'last_day_of_month'],
                'shift' => ['next', 'weekday'],
                'if' => ['not', 'weekend'],
                'times' => ['10:00'],
            ]],
            'allday' => [['days' => ['mon'], 'allday' => true]],
            'every 2 days' => [[
                'from' => '2026-07-14 00:00',
                'days' => [['every', 2, 'day']],
                'times' => ['03:00'],
            ]],
            'the interval every' => [['from' => '2026-07-17 10:00', 'every' => [7, 'hour']]],
            'a second-denominated interval every stays as written' => [['from' => '2026-07-14 00:00', 'every' => [172800, 'second']]],
            'until alone' => [['until' => '2026-12-31 23:59', 'times' => ['09:00']]],
        ];
    }

    /**
     * @param  int|string|array{string, string}  $atom
     */
    #[Test]
    #[DataProvider('dayAtoms')]
    public function a_day_atom_round_trip_is_the_identity(int|string|array $atom): void
    {
        $raw = ['days' => [$atom], 'times' => ['09:00']];

        $this->assertSame($raw, (new YrnkScheduleBuilder())->build((new YrnkScheduleParser())->parse($raw, self::tz())));
    }

    /**
     * @return array<string, list<int|string|array{string, string}>>
     */
    public static function dayAtoms(): array
    {
        return [
            'day of month' => [25],
            'Monday' => ['mon'], 'Tuesday' => ['tue'], 'Wednesday' => ['wed'], 'Thursday' => ['thu'],
            'Friday' => ['fri'], 'Saturday' => ['sat'], 'Sunday' => ['sun'],
            'weekday' => ['weekday'], 'weekend' => ['weekend'], 'holiday' => ['holiday'],
            'business_day' => ['business_day'], 'business_holiday' => ['business_holiday'],
            '1st' => [['1st', 'fri']], '2nd' => [['2nd', 'fri']], '3rd' => [['3rd', 'fri']],
            '4th' => [['4th', 'fri']], '5th' => [['5th', 'fri']], 'last' => [['last', 'fri']],
            'end of month' => ['last_day_of_month'],
            'a date set name' => ['fête-nationale'],
        ];
    }

    /**
     * @param  list<mixed>  $shift
     */
    #[Test]
    #[DataProvider('shifts')]
    public function a_shift_round_trip_is_the_identity(array $shift): void
    {
        $raw = ['days' => [25], 'shift' => $shift, 'times' => ['09:00']];

        $this->assertSame($raw, (new YrnkScheduleBuilder())->build((new YrnkScheduleParser())->parse($raw, self::tz())));
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    public static function shifts(): array
    {
        return [
            'exclusive prev' => [['prev', 'business_day']],
            'inclusive prev' => [['prev', 'or_same', 'business_day']],
            'exclusive next' => [['next', 'weekday']],
            'inclusive next' => [['next', 'or_same', 'weekday']],
            'a tuple landing condition' => [['prev', ['last', 'fri']]],
        ];
    }

    /**
     * @param  list<mixed>  $if
     */
    #[Test]
    #[DataProvider('ifGuards')]
    public function an_if_round_trip_is_the_identity(array $if): void
    {
        $raw = ['days' => [13], 'if' => $if, 'times' => ['09:00']];

        $this->assertSame($raw, (new YrnkScheduleBuilder())->build((new YrnkScheduleParser())->parse($raw, self::tz())));
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    public static function ifGuards(): array
    {
        return [
            'the condition alone' => [['fri']],
            'not' => [['not', 'holiday']],
            'a direction' => [['next', 'business_holiday']],
            'a direction and not' => [['prev', 'not', 'holiday']],
        ];
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    #[Test]
    #[DataProvider('timesForms')]
    public function a_times_round_trip_is_the_identity(array $schedule): void
    {
        $this->assertSame($schedule, (new YrnkScheduleBuilder())->build((new YrnkScheduleParser())->parse($schedule, self::tz())));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function timesForms(): array
    {
        return [
            'fixed times in written order' => [['times' => ['12:00', '09:00', '18:30']]],
            'every hour' => [['times' => ['every' => [1, 'hour']]]],
            'every minute' => [['times' => ['every' => [90, 'minute']]]],
            'every second' => [['times' => ['every' => [600, 'second']]]],
            'a between pair' => [['times' => ['every' => [1, 'hour'], 'between' => ['08:00', '20:00']]]],
            'a between ending at 24:00' => [['times' => ['every' => [1, 'hour'], 'between' => ['22:00', '24:00']]]],
            'between business_hour' => [['times' => ['every' => [1, 'hour'], 'between' => 'business_hour']]],
            'allday' => [['allday' => true]],
        ];
    }

    /**
     * @param  array<string, mixed>  $calendar
     * @param  list<string>  $resolvers
     */
    #[Test]
    #[DataProvider('definitionsForms')]
    public function a_definitions_round_trip_is_the_identity(array $calendar, array $resolvers = []): void
    {
        $raw = [
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            ...($resolvers === [] ? [] : ['resolvers' => $resolvers]),
            'calendar' => $calendar,
            'schedules' => [['times' => ['09:00']]],
        ];
        $parser = new YrnkParser(Bindings::of(['yasumi-jp' => Bindings::returning([])]));

        $this->assertSame($raw, (new YrnkBuilder())->build($parser->parse($raw)));
    }

    /**
     * @return array<string, list<array<string, mixed>|list<string>>>
     */
    public static function definitionsForms(): array
    {
        return [
            'holidays' => [['holidays' => ['2026-01-01', '2026-01-12']]],
            'business_holidays' => [['business_holidays' => ['2026-08-13']]],
            'business_days' => [['business_days' => ['2026-07-11']]],
            'workweek' => [['workweek' => ['tue', 'wed', 'thu', 'fri', 'sat']]],
            'business_hours' => [['business_hours' => [['09:00', '12:00'], ['13:00', '18:00']]]],
            'a resolver name' => [['holidays' => 'yasumi-jp'], ['yasumi-jp']],
            'several date_sets entries' => [['date_sets' => ['founding-day' => ['2026-10-01'], 'garbage-day' => ['2026-07-03', '2026-07-17']]]],
        ];
    }


    #[Test]
    public function to_json_parses_back_to_the_same_meaning(): void
    {
        $raw = [
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'calendar' => ['date_sets' => ['anniversary' => ['2026-10-01']]],
            'schedules' => [['days' => ['anniversary'], 'times' => ['09:00']]],
        ];
        $parser = new YrnkParser();

        $json = (new YrnkBuilder())->toJson($parser->parse($raw));

        $this->assertSame($raw, (new YrnkBuilder())->build($parser->parse($json)));
    }

    private static function tz(): DateTimeZone
    {
        return new DateTimeZone('Asia/Tokyo');
    }
}
