<?php

namespace Yarunoka\Tests\Unit\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\ReservedNameException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Exceptions\UnsupportedVersionException;
use Yarunoka\Parser\YrnkParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YrnkParserTest extends TestCase
{
    // ---- the whole document ----

    #[Test]
    public function parses_a_complete_document(): void
    {
        $parser = new YrnkParser(resolvers: ['yasumi-jp' => fn(): array => ['2026-01-01']]);

        $document = $parser->parse([
            'version' => 1,
            'timezone' => 'Asia/Tokyo',
            'definitions' => [
                'holidays' => 'yasumi-jp',
                'business_holidays' => [],
                'business_days' => [],
                'workweek' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'business_hours' => [['09:00', '12:00'], ['13:00', '18:00']],
                'custom' => ['founding-day' => ['2026-10-01']],
            ],
            'schedules' => [
                ['days' => ['holiday'], 'times' => ['08:00']],
                ['days' => ['founding-day'], 'allday' => true],
            ],
        ]);

        $this->assertSame(1, $document->version);
        $this->assertSame('Asia/Tokyo', $document->timezone->getName());
        $this->assertSame('yasumi-jp', $document->definitions->holidays?->resolver);
        $this->assertArrayHasKey('founding-day', $document->definitions->custom);
        $this->assertCount(2, $document->schedules);
    }

    #[Test]
    public function parses_from_a_json_string(): void
    {
        $document = (new YrnkParser())->parse(
            '{"version": 1, "timezone": "Asia/Tokyo", "schedules": [{"times": ["09:00"]}]}',
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

        (new YrnkParser())->parse($this->doc(['version' => 2]));
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

    // ---- definitions ----

    #[Test]
    public function rejects_an_unknown_definitions_key(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['definitions' => ['holiday' => []]]));
    }

    #[Test]
    public function rejects_a_reserved_word_as_a_custom_key(): void
    {
        $this->expectException(ReservedNameException::class);

        (new YrnkParser())->parse($this->doc(['definitions' => ['custom' => ['holiday' => ['2026-01-01']]]]));
    }

    #[Test]
    public function rejects_a_date_shaped_custom_key(): void
    {
        $this->expectException(ReservedNameException::class);

        (new YrnkParser())->parse($this->doc(['definitions' => ['custom' => ['2026-01-01' => ['2026-01-01']]]]));
    }

    #[Test]
    public function rejects_a_single_date_string_as_a_custom_value(): void
    {
        // Scalar sugar is removed. Even a single date is written as an
        // array.
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['definitions' => ['custom' => ['anniversary' => '2026-10-01']]]));
    }

    #[Test]
    public function rejects_a_workweek_with_an_invalid_day_name(): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($this->doc(['definitions' => ['workweek' => ['monday']]]));
    }

    #[Test]
    public function a_reference_to_an_unregistered_resolver_name_raises(): void
    {
        $this->expectException(UndefinedNameException::class);

        (new YrnkParser())->parse($this->doc(['definitions' => ['holidays' => 'yasumi-jp']]));
    }

    #[Test]
    public function a_custom_value_can_reference_a_resolver_name_too(): void
    {
        $parser = new YrnkParser(resolvers: ['garbage-days' => fn(): array => []]);

        $document = $parser->parse($this->doc([
            'definitions' => ['custom' => ['garbage-day' => 'garbage-days']],
        ]));

        $this->assertSame('garbage-days', $document->definitions->custom['garbage-day']->resolver);
    }

    // ---- resolvability of references ----

    #[Test]
    public function a_reference_to_an_undefined_custom_name_raises(): void
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
            'definitions' => ['holidays' => []],
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
                'version' => 1,
                'timezone' => 'Asia/Tokyo',
                'schedules' => [['times' => ['09:00']]],
            ],
            ...$overrides,
        ];
    }
}
