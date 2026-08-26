<?php

namespace Yarunoka\Tests\Unit;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\ReservedNameException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Exceptions\UnregisteredResolverException;
use Yarunoka\Exceptions\UnsupportedVersionException;
use Yarunoka\YrnkParser;
use Yarunoka\Tests\Support\Bindings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YrnkParserTest extends TestCase
{
    // ---- the whole document ----

    #[Test]
    public function parses_a_complete_document(): void
    {
        $parser = new YrnkParser(Bindings::of(['yasumi-jp' => Bindings::returning(['2026-01-01'])]));

        $document = $parser->parse([
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'resolvers' => ['yasumi-jp'],
            'calendar' => [
                'holidays' => 'yasumi-jp',
                'business_holidays' => [],
                'business_days' => [],
                'workweek' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'business_hours' => [['09:00', '12:00'], ['13:00', '18:00']],
                'date_sets' => ['founding-day' => ['2026-10-01']],
            ],
            'schedules' => [
                ['days' => ['holiday'], 'times' => ['08:00']],
                ['days' => ['founding-day'], 'allday' => true],
            ],
        ]);

        $this->assertSame('1.0', $document->version);
        $this->assertSame('Asia/Tokyo', $document->timezone->getName());
        $this->assertSame('yasumi-jp', $document->calendar->holidays);
        $this->assertArrayHasKey('founding-day', $document->calendar->dateSets);
        $this->assertCount(2, $document->schedules);
    }

    #[Test]
    public function parses_from_a_json_string(): void
    {
        $document = (new YrnkParser())->parse(
            '{"version": "1.0", "timezone": "Asia/Tokyo", "schedules": [{"times": ["09:00"]}]}',
        );

        $this->assertCount(1, $document->schedules);
    }

    #[Test]
    public function rejects_invalid_json(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse('{');
    }

    #[Test]
    public function rejects_a_document_writing_a_member_name_twice(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse(
            '{"version": "1.1", "timezone": "Asia/Tokyo", "timezone": "UTC", "schedules": [{"allday": true}]}',
        );
    }

    #[Test]
    public function rejects_a_member_name_colliding_through_an_escape(): void
    {
        // JSON decides member equality on the resolved characters, never
        // on the written bytes.
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse(
            '{"version": "1.1", "timezone": "Asia/Tokyo", "\u0074imezone": "UTC", "schedules": [{"allday": true}]}',
        );
    }

    #[Test]
    public function rejects_a_duplicate_member_name_in_a_schedule(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse(
            '{"version": "1.1", "timezone": "Asia/Tokyo", "schedules": [{"days": [1], "days": [2], "times": ["09:00"]}]}',
        );
    }

    #[Test]
    public function a_duplicate_member_name_is_rejected_whatever_version_the_document_declares(): void
    {
        // A determination of behavior 1.0 left undefined, so it reaches
        // documents declaring 1.0 too.
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse(
            '{"version": "1.0", "timezone": "Asia/Tokyo", "timezone": "UTC", "schedules": [{"allday": true}]}',
        );
    }

    #[Test]
    public function rejects_an_empty_calendar_object_in_a_1_1_document(): void
    {
        // A document with no definitions omits the key, so that the
        // statement has a single spelling.
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['version' => '1.1', 'calendar' => []]));
    }

    #[Test]
    public function rejects_an_empty_date_sets_object_in_a_1_1_document(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['version' => '1.1', 'calendar' => ['date_sets' => []]]));
    }

    #[Test]
    public function accepts_an_empty_calendar_object_in_a_1_0_document(): void
    {
        // Validity follows the declared version: under 1.0's rules an
        // empty calendar means the same as omitting the key.
        $document = (new YrnkParser())->parse($this->doc(['version' => '1.0', 'calendar' => []]));

        $this->assertSame([], $document->calendar->dateSets);
    }

    #[Test]
    public function accepts_an_empty_date_sets_object_in_a_1_0_document(): void
    {
        $document = (new YrnkParser())->parse($this->doc([
            'version' => '1.0',
            'calendar' => ['holidays' => ['2026-01-01'], 'date_sets' => []],
        ]));

        $this->assertSame([], $document->calendar->dateSets);
    }

    #[Test]
    public function accepts_the_day_cycle_count_at_the_1_1_bound(): void
    {
        $document = (new YrnkParser())->parse($this->doc(['version' => '1.1', 'schedules' => [
            ['from' => '0001-01-01 00:00', 'days' => [['every', 3652058, 'day']], 'times' => ['09:00']],
        ]]));

        $this->assertSame('1.1', $document->version);
    }

    #[Test]
    public function rejects_a_day_cycle_count_beyond_the_1_1_bound(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['version' => '1.1', 'schedules' => [
            ['from' => '0001-01-01 00:00', 'days' => [['every', 3652059, 'day']], 'times' => ['09:00']],
        ]]));
    }

    #[Test]
    public function accepts_a_day_cycle_count_beyond_the_bound_in_a_1_0_document(): void
    {
        // 1.0's counts have no upper bound; under the closed date domain
        // an over-bound count enumerates the from day alone.
        $document = (new YrnkParser())->parse($this->doc(['version' => '1.0', 'schedules' => [
            ['from' => '2026-01-01 00:00', 'days' => [['every', 3652059, 'day']], 'times' => ['09:00']],
        ]]));

        $this->assertSame('1.0', $document->version);
    }

    #[Test]
    #[DataProvider('sequenceCountsAtTheBound')]
    public function accepts_a_sequence_count_at_the_1_1_bound(int $count, string $unit): void
    {
        $document = (new YrnkParser())->parse($this->doc(['version' => '1.1', 'schedules' => [
            ['from' => '0001-01-01 00:00', 'every' => [$count, $unit]],
        ]]));

        $this->assertSame('1.1', $document->version);
    }

    #[Test]
    #[DataProvider('sequenceCountsAtTheBound')]
    public function rejects_a_sequence_count_beyond_the_1_1_bound(int $count, string $unit): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['version' => '1.1', 'schedules' => [
            ['from' => '0001-01-01 00:00', 'every' => [$count + 1, $unit]],
        ]]));
    }

    #[Test]
    public function accepts_a_sequence_count_beyond_the_bound_in_a_1_0_document(): void
    {
        $document = (new YrnkParser())->parse($this->doc(['version' => '1.0', 'schedules' => [
            ['from' => '2026-01-01 00:00', 'every' => [87649416, 'hour']],
        ]]));

        $this->assertSame('1.0', $document->version);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function sequenceCountsAtTheBound(): array
    {
        return [
            'hour' => [87649415, 'hour'],
            'minute' => [5258964959, 'minute'],
            'second' => [315537897599, 'second'],
        ];
    }

    #[Test]
    public function rejects_an_unknown_document_key(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['schedule' => []]));
    }

    #[Test]
    public function rejects_a_missing_version(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse(['timezone' => 'Asia/Tokyo', 'schedules' => [['allday' => true]]]);
    }

    #[Test]
    public function an_unknown_version_raises(): void
    {
        $this->expectException(UnsupportedVersionException::class);

        (new YrnkParser())->parse($this->doc(['version' => '2.0']));
    }

    #[Test]
    public function accepts_a_document_declaring_1_1(): void
    {
        $document = (new YrnkParser())->parse($this->doc(['version' => '1.1']));

        $this->assertSame('1.1', $document->version);
    }

    #[Test]
    public function an_unknown_newer_minor_version_raises(): void
    {
        $this->expectException(UnsupportedVersionException::class);

        (new YrnkParser())->parse($this->doc(['version' => '1.2']));
    }

    #[Test]
    public function accepts_a_timezone_with_dst(): void
    {
        $document = (new YrnkParser())->parse($this->doc(['timezone' => 'Europe/London']));

        $this->assertSame('Europe/London', $document->timezone->getName());
    }

    #[Test]
    public function rejects_a_timezone_that_does_not_exist(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['timezone' => 'Asia/Edo']));
    }

    #[Test]
    public function rejects_a_fixed_offset_timezone(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['timezone' => '+09:00']));
    }

    #[Test]
    public function rejects_a_timezone_abbreviation(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['timezone' => 'JST']));
    }

    #[Test]
    public function accepts_a_backward_link_timezone(): void
    {
        $document = (new YrnkParser())->parse($this->doc(['timezone' => 'Japan']));

        $this->assertSame('Japan', $document->timezone->getName());
    }

    #[Test]
    public function rejects_a_bare_object_as_schedules(): void
    {
        // The same decision as removing scalar sugar: always written as a
        // list.
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['schedules' => ['times' => ['09:00']]]));
    }

    #[Test]
    public function rejects_empty_schedules(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['schedules' => []]));
    }

    #[Test]
    public function rejects_duplicate_schedules(): void
    {
        $this->expectException(InvalidYrnkException::class);
        $this->expectExceptionMessage('Duplicate schedule in schedules');

        (new YrnkParser())->parse($this->doc(['schedules' => [
            ['days' => ['mon'], 'times' => ['10:00']],
            ['days' => ['mon'], 'times' => ['10:00']],
        ]]));
    }

    #[Test]
    public function rejects_duplicate_schedules_spelled_in_a_different_member_order(): void
    {
        // JSON object equality has no member order, so uniqueItems does
        // not either; the two spellings are one schedule.
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['schedules' => [
            ['days' => ['mon'], 'times' => ['10:00']],
            ['times' => ['10:00'], 'days' => ['mon']],
        ]]));
    }

    // ---- calendar ----

    #[Test]
    public function rejects_an_unknown_calendar_key(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['calendar' => ['holiday' => []]]));
    }

    #[Test]
    public function rejects_a_reserved_word_as_a_date_sets_key(): void
    {
        $this->expectException(ReservedNameException::class);

        (new YrnkParser())->parse($this->doc(['calendar' => ['date_sets' => ['holiday' => ['2026-01-01']]]]));
    }

    #[Test]
    public function rejects_a_date_shaped_date_sets_key(): void
    {
        $this->expectException(ReservedNameException::class);

        (new YrnkParser())->parse($this->doc(['calendar' => ['date_sets' => ['2026-01-01' => ['2026-01-01']]]]));
    }

    #[Test]
    public function rejects_a_single_date_string_as_a_date_sets_value(): void
    {
        // Scalar sugar is removed. Even a single date is written as an
        // array.
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['calendar' => ['date_sets' => ['anniversary' => '2026-10-01']]]));
    }

    #[Test]
    public function rejects_a_workweek_with_an_invalid_day_name(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['calendar' => ['workweek' => ['monday']]]));
    }

    #[Test]
    public function a_declared_name_the_host_never_bound_raises(): void
    {
        $this->expectException(UnregisteredResolverException::class);

        (new YrnkParser())->parse($this->doc([
            'resolvers' => ['yasumi-jp'],
            'calendar' => ['holidays' => 'yasumi-jp'],
        ]));
    }

    // ---- resolvers — the names left to the host ----

    #[Test]
    public function parses_the_declared_names(): void
    {
        $parser = new YrnkParser(Bindings::of(['garbage-days' => Bindings::returning([])]));

        $document = $parser->parse($this->doc([
            'resolvers' => ['garbage-days'],
            'schedules' => [['days' => ['garbage-days'], 'times' => ['09:00']]],
        ]));

        $this->assertSame(['garbage-days'], $document->resolvers);
    }

    #[Test]
    public function a_declared_name_need_not_be_used(): void
    {
        $parser = new YrnkParser(Bindings::of(['next-year' => Bindings::returning([])]));

        $document = $parser->parse($this->doc(['resolvers' => ['next-year']]));

        $this->assertSame(['next-year'], $document->resolvers);
    }

    #[Test]
    public function a_used_and_undefined_name_must_be_declared(): void
    {
        // Even a name the host happens to bind: the declaration is what
        // makes the requirement readable from the document alone.
        $parser = new YrnkParser(Bindings::of(['garbage-days' => Bindings::returning([])]));

        $this->expectException(UndefinedNameException::class);

        $parser->parse($this->doc([
            'schedules' => [['days' => ['garbage-days'], 'times' => ['09:00']]],
        ]));
    }

    #[Test]
    public function a_declared_name_cannot_also_be_a_date_sets_key(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc([
            'resolvers' => ['founding-day'],
            'calendar' => ['date_sets' => ['founding-day' => ['2026-10-01']]],
        ]));
    }

    #[Test]
    public function every_declared_name_the_host_left_unbound_is_reported_at_once(): void
    {
        try {
            (new YrnkParser())->parse($this->doc(['resolvers' => ['one', 'two', 'three']]));
            $this->fail('UnregisteredResolverException was not thrown');
        } catch (UnregisteredResolverException $e) {
            $this->assertStringContainsString('one', $e->getMessage());
            $this->assertStringContainsString('two', $e->getMessage());
            $this->assertStringContainsString('three', $e->getMessage());
        }
    }

    #[Test]
    public function an_empty_declaration_list_is_rejected(): void
    {
        // "requires nothing" has a single spelling: the key is omitted.
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['resolvers' => []]));
    }

    #[Test]
    public function a_duplicate_declaration_is_rejected(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['resolvers' => ['garbage-days', 'garbage-days']]));
    }

    #[Test]
    public function a_declared_name_follows_the_spelling_rule(): void
    {
        $this->expectException(ReservedNameException::class);

        (new YrnkParser())->parse($this->doc(['resolvers' => ['mon']]));
    }

    #[Test]
    public function a_date_set_value_cannot_be_a_name(): void
    {
        // The entry is where the document holds the dates it names, so it
        // never stands for another name (no definition macros).
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc([
            'calendar' => ['date_sets' => ['garbage-day' => 'garbage-days']],
        ]));
    }

    #[Test]
    public function a_date_list_position_accepts_the_name_of_a_date_set(): void
    {
        $document = (new YrnkParser())->parse($this->doc([
            'calendar' => [
                'holidays' => 'founding-day',
                'date_sets' => ['founding-day' => ['2026-10-01']],
            ],
            'schedules' => [['days' => ['holiday'], 'times' => ['09:00']]],
        ]));

        $this->assertSame('founding-day', $document->calendar->holidays);
    }

    #[Test]
    public function a_resolver_name_can_be_written_in_days(): void
    {
        $parser = new YrnkParser(Bindings::of(['garbage-days' => Bindings::returning(['2026-10-01'])]));

        $document = $parser->parse($this->doc([
            'resolvers' => ['garbage-days'],
            'schedules' => [['days' => ['garbage-days'], 'times' => ['09:00']]],
        ]));

        $this->assertCount(1, $document->schedules);
    }

    #[Test]
    public function a_reserved_word_cannot_be_a_name_in_a_date_list_position(): void
    {
        // One namespace, one spelling rule: a name written as a date-set
        // value is held to what every other name is held to.
        $this->expectException(ReservedNameException::class);

        (new YrnkParser())->parse($this->doc(['calendar' => ['holidays' => 'mon']]));
    }

    // ---- resolvability of references ----

    #[Test]
    public function a_reference_to_an_undefined_name_raises(): void
    {
        $this->expectException(UndefinedNameException::class);

        (new YrnkParser())->parse($this->doc([
            'schedules' => [['days' => ['founding-day'], 'times' => ['09:00']]],
        ]));
    }

    #[Test]
    public function a_document_using_holiday_raises_without_the_holidays_definition(): void
    {
        $this->expectException(MissingCalendarDataException::class);

        (new YrnkParser())->parse($this->doc([
            'schedules' => [['days' => ['holiday'], 'times' => ['09:00']]],
        ]));
    }

    #[Test]
    public function business_day_requires_all_three_layer_definitions(): void
    {
        $this->expectException(MissingCalendarDataException::class);

        (new YrnkParser())->parse($this->doc([
            'calendar' => ['holidays' => []],
            'schedules' => [['days' => ['business_day'], 'times' => ['09:00']]],
        ]));
    }

    #[Test]
    public function the_vocabulary_in_a_shift_landing_condition_is_reference_checked_too(): void
    {
        $this->expectException(MissingCalendarDataException::class);

        (new YrnkParser())->parse($this->doc([
            'schedules' => [['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['09:00']]],
        ]));
    }

    #[Test]
    public function the_vocabulary_in_an_if_condition_is_reference_checked_too(): void
    {
        $this->expectException(MissingCalendarDataException::class);

        (new YrnkParser())->parse($this->doc([
            'schedules' => [['days' => ['mon'], 'if' => ['not', 'holiday'], 'times' => ['09:00']]],
        ]));
    }

    #[Test]
    public function a_document_using_business_hour_requires_the_business_hours_definition(): void
    {
        $this->expectException(MissingCalendarDataException::class);

        (new YrnkParser())->parse($this->doc([
            'schedules' => [['times' => ['every' => [1, 'hour'], 'between' => 'business_hour']]],
        ]));
    }

    #[Test]
    public function weekday_alone_parses_without_any_definition(): void
    {
        $document = (new YrnkParser())->parse($this->doc([
            'schedules' => [['days' => ['weekday'], 'times' => ['09:00']]],
        ]));

        $this->assertCount(1, $document->schedules);
    }

    // ---- helpers ----

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function doc(array $overrides = []): array
    {
        return [
            ...[
                'version' => '1.0',
                'timezone' => 'Asia/Tokyo',
                'schedules' => [['times' => ['09:00']]],
            ],
            ...$overrides,
        ];
    }
}
