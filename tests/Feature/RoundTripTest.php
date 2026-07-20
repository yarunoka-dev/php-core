<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Builder\ScheduleBuilder;
use Yarunoka\Builder\YrnkBuilder;
use Yarunoka\Definitions\Definitions;
use Yarunoka\Definitions\Holidays;
use Yarunoka\Expression\AllDay;
use Yarunoka\Parser\ScheduleParser;
use Yarunoka\Parser\YrnkParser;
use Yarunoka\Yrnk;
use Yarunoka\YrnkSchedule;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $parser = new YrnkParser(resolvers: [
            'yasumi-jp' => static fn(): array => ['2026-01-01'],
            'garbage-days' => static fn(): array => [],
        ]);

        $this->assertSame($raw, (new YrnkBuilder())->build($parser->parse($raw)));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    #[Test]
    #[DataProvider('schedules')]
    public function a_single_schedule_round_trip_is_the_identity(array $raw): void
    {
        $this->assertSame($raw, (new ScheduleBuilder())->build((new ScheduleParser())->parse($raw)));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function documents(): array
    {
        return [
            'full definitions with a payday and an anniversary' => [[
                'version' => 1,
                'timezone' => 'Asia/Tokyo',
                'definitions' => [
                    'holidays' => ['2026-01-01', '2026-01-12'],
                    'business_holidays' => [],
                    'business_days' => [],
                    'workweek' => ['tue', 'wed', 'thu', 'fri', 'sat'],
                    'business_hours' => [['09:00', '12:00'], ['13:00', '18:00']],
                    'custom' => ['founding-day' => ['2026-10-01']],
                ],
                'schedules' => [
                    ['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']],
                    ['days' => ['founding-day'], 'allday' => true],
                ],
            ]],
            'resolver name references' => [[
                'version' => 1,
                'timezone' => 'Asia/Tokyo',
                'definitions' => [
                    'holidays' => 'yasumi-jp',
                    'custom' => ['garbage-day' => 'garbage-days'],
                ],
                'schedules' => [
                    ['days' => ['holiday'], 'times' => ['08:00']],
                ],
            ]],
            'notation preservation (times order and every unit)' => [[
                'version' => 1,
                'timezone' => 'UTC',
                'schedules' => [
                    ['times' => ['12:00', '09:00']],
                    ['days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                        'times' => ['every' => [90, 'minute'], 'between' => ['08:30', '24:00']]],
                ],
            ]],
            'a business_hour reference and if' => [[
                'version' => 1,
                'timezone' => '+09:00',
                'definitions' => [
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

        $this->assertSame($raw, (new ScheduleBuilder())->build((new ScheduleParser())->parse($raw)));
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
            'a custom name' => ['fête-nationale'],
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

        $this->assertSame($raw, (new ScheduleBuilder())->build((new ScheduleParser())->parse($raw)));
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

        $this->assertSame($raw, (new ScheduleBuilder())->build((new ScheduleParser())->parse($raw)));
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
        $this->assertSame($schedule, (new ScheduleBuilder())->build((new ScheduleParser())->parse($schedule)));
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
     * @param  array<string, mixed>  $definitions
     */
    #[Test]
    #[DataProvider('definitionsForms')]
    public function a_definitions_round_trip_is_the_identity(array $definitions): void
    {
        $raw = [
            'version' => 1,
            'timezone' => 'Asia/Tokyo',
            'definitions' => $definitions,
            'schedules' => [['times' => ['09:00']]],
        ];
        $parser = new YrnkParser(resolvers: ['yasumi-jp' => static fn(): array => []]);

        $this->assertSame($raw, (new YrnkBuilder())->build($parser->parse($raw)));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function definitionsForms(): array
    {
        return [
            'holidays' => [['holidays' => ['2026-01-01', '2026-01-12']]],
            'business_holidays' => [['business_holidays' => ['2026-08-13']]],
            'business_days' => [['business_days' => ['2026-07-11']]],
            'workweek' => [['workweek' => ['tue', 'wed', 'thu', 'fri', 'sat']]],
            'business_hours' => [['business_hours' => [['09:00', '12:00'], ['13:00', '18:00']]]],
            'a resolver name' => [['holidays' => 'yasumi-jp']],
            'several custom entries' => [['custom' => ['founding-day' => ['2026-10-01'], 'garbage-day' => ['2026-07-03', '2026-07-17']]]],
        ];
    }

    #[Test]
    public function a_hand_composed_deferred_is_folded_into_a_snapshot(): void
    {
        // A Closure is not writable in the DSL, so it is outside the
        // identity; build outputs it as the resolved list.
        $document = new Yrnk(
            version: 1,
            timezone: new DateTimeZone('Asia/Tokyo'),
            definitions: new Definitions(
                holidays: Holidays::deferred(static fn(): array => ['2026-01-01']),
            ),
            schedules: [new YrnkSchedule(times: new AllDay())],
        );

        $built = (new YrnkBuilder())->build($document);

        $this->assertSame(['holidays' => ['2026-01-01']], $built['definitions'] ?? null);
    }

    #[Test]
    public function to_json_parses_back_to_the_same_meaning(): void
    {
        $raw = [
            'version' => 1,
            'timezone' => 'Asia/Tokyo',
            'definitions' => ['custom' => ['anniversary' => ['2026-10-01']]],
            'schedules' => [['days' => ['anniversary'], 'times' => ['09:00']]],
        ];
        $parser = new YrnkParser();

        $json = (new YrnkBuilder())->toJson($parser->parse($raw));

        $this->assertSame($raw, (new YrnkBuilder())->build($parser->parse($json)));
    }
}
