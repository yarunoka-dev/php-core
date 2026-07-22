<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Exceptions\YarunokaException;
use Yarunoka\Parser\YrnkParser;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the JSON Schema (the authority on the syntax) and
 * YrnkParser agree.
 *
 * - Legal documents: both accept
 * - Syntax violations: both reject
 * - Constraints beyond the schema (resolvability of references, window
 *   start < end and non-overlap, existence of dates and the timezone):
 *   the schema passes them and the implementation rejects them at parse
 *   time. That list is the inventory of what the schema alone cannot
 *   validate
 */
class SchemaConformanceTest extends TestCase
{
    private const string SCHEMA_ID = 'https://github.com/yarunoka-dev/php-core/schema/v1';

    #[Test]
    #[DataProvider('validDocuments')]
    public function a_legal_document_is_accepted_by_both_the_schema_and_the_implementation(string $json): void
    {
        $this->assertTrue($this->schemaAccepts($json));
        $this->parser()->parse($json);
        $this->addToAssertionCount(1); // parsed without an exception
    }

    #[Test]
    #[DataProvider('syntaxInvalidDocuments')]
    public function a_syntax_violation_is_rejected_by_the_schema(string $json): void
    {
        $this->assertFalse($this->schemaAccepts($json));
    }

    #[Test]
    #[DataProvider('syntaxInvalidDocuments')]
    public function a_syntax_violation_is_rejected_by_the_implementation_too(string $json): void
    {
        $this->expectException(YarunokaException::class);

        $this->parser()->parse($json);
    }

    #[Test]
    #[DataProvider('semanticInvalidDocuments')]
    public function a_constraint_beyond_the_schema_passes_the_schema(string $json): void
    {
        $this->assertTrue($this->schemaAccepts($json));
    }

    #[Test]
    #[DataProvider('semanticInvalidDocuments')]
    public function a_constraint_beyond_the_schema_is_rejected_by_the_implementation_at_parse_time(string $json): void
    {
        $this->expectException(YarunokaException::class);

        $this->parser()->parse($json);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validDocuments(): array
    {
        return [
            'the minimal document' => [self::doc('{"times": ["09:00"]}')],
            'the third Monday of every month at 10:00' => [self::doc('{"days": [["3rd", "mon"]], "times": ["10:00"]}')],
            'hourly on weekdays from 8 to 20' => [self::doc(
                '{"days": ["mon", "tue", "wed", "thu", "fri"], "times": {"every": [1, "hour"], "between": ["08:00", "20:00"]}}',
            )],
            'every 600 seconds' => [self::doc('{"times": {"every": [600, "second"]}}')],
            'the payday prev shift' => [self::doc(
                '{"days": [25], "shift": ["prev", "or_same", "business_day"], "times": ["10:00"]}',
                definitions: '{"holidays": [], "business_holidays": [], "business_days": []}',
            )],
            'the day-before-a-break if' => [self::doc(
                '{"days": ["business_day"], "if": ["next", "business_holiday"], "times": ["08:00"]}',
                definitions: '{"holidays": ["2026-01-01"], "business_holidays": [], "business_days": []}',
            )],
            'the holiday-skipping if' => [self::doc(
                '{"days": ["mon"], "if": ["not", "holiday"], "times": ["07:30"]}',
                definitions: '{"holidays": "yasumi-jp"}',
            )],
            'the day before the end of the month (if without days)' => [self::doc('{"if": ["next", "last_day_of_month"], "times": ["09:00"]}')],
            'an allday on a specific date' => [self::doc('{"years": [2043], "months": [6], "days": [15], "allday": true}')],
            'between business_hour' => [self::doc(
                '{"times": {"every": [1, "hour"], "between": "business_hour"}}',
                definitions: '{"business_hours": [["09:00", "12:00"], ["13:00", "18:00"]]}',
            )],
            'a window ending at 24:00' => [self::doc('{"times": {"every": [1, "hour"], "between": ["22:00", "24:00"]}}')],
            'a custom definition and workweek' => [self::doc(
                '{"days": ["founding-day"], "allday": true}',
                definitions: '{"workweek": ["tue", "wed", "thu", "fri", "sat"], "custom": {"founding-day": ["2026-10-01"]}}',
            )],
            'a timezone with DST' => ['{"version": 1, "timezone": "Europe/London", "schedules": [{"times": ["09:00"]}]}'],
            'the per-unit maximum of every (hours)' => [self::doc('{"times": {"every": [24, "hour"]}}')],
            'the minute maximum of every' => [self::doc('{"times": {"every": [1440, "minute"]}}')],
            'the second maximum of every' => [self::doc('{"times": {"every": [86400, "second"]}}')],
            'every 2 days (the motivating 172800 seconds)' => [self::doc(
                '{"from": "2026-07-14 00:00", "days": [["every", 2, "day"]], "times": ["03:00"]}',
            )],
            'a cycle atom alongside another atom' => [self::doc(
                '{"from": "2026-07-14 00:00", "days": [["every", 2, "day"], "mon"], "times": ["10:00"]}',
            )],
            'the interval every of 36 hours' => [self::doc('{"from": "2026-07-14 00:00", "every": [36, "hour"]}')],
            'an hourly interval starting at 10:00 the next day' => [self::doc('{"from": "2026-07-15 10:00", "every": [1, "hour"]}')],
            'the interval every accepts more than a day of seconds' => [self::doc('{"from": "2026-07-14 00:00", "every": [172800, "second"]}')],
            'every Monday with from and until' => [self::doc(
                '{"from": "2026-08-01 00:00", "until": "2026-09-01 00:00", "days": ["mon"], "times": ["10:00"]}',
            )],
            'a deadline with until alone' => [self::doc('{"until": "2026-12-31 23:59", "times": ["09:00"]}')],
            'a bounded interval every' => [self::doc(
                '{"from": "2026-07-14 00:00", "until": "2026-08-01 00:00", "every": [36, "hour"]}',
            )],
            'a bounded every 2 days' => [self::doc(
                '{"from": "2026-07-14 00:00", "until": "2026-08-01 00:00", "days": [["every", 2, "day"]], "times": ["03:00"]}',
            )],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function syntaxInvalidDocuments(): array
    {
        return [
            'a string version' => ['{"version": "1", "timezone": "Asia/Tokyo", "schedules": [{"times": ["09:00"]}]}'],
            'an unknown version' => ['{"version": 2, "timezone": "Asia/Tokyo", "schedules": [{"times": ["09:00"]}]}'],
            'a missing timezone' => ['{"version": 1, "schedules": [{"times": ["09:00"]}]}'],
            'a whitespace-only timezone' => ['{"version": 1, "timezone": "   ", "schedules": [{"times": ["09:00"]}]}'],
            'missing schedules' => ['{"version": 1, "timezone": "Asia/Tokyo"}'],
            'empty schedules' => ['{"version": 1, "timezone": "Asia/Tokyo", "schedules": []}'],
            'a bare object as schedules' => ['{"version": 1, "timezone": "Asia/Tokyo", "schedules": {"times": ["09:00"]}}'],
            'an unknown document key' => ['{"version": 1, "timezone": "Asia/Tokyo", "schedule": [], "schedules": [{"times": ["09:00"]}]}'],
            'an unknown schedule key' => [self::doc('{"times": ["09:00"], "day": ["mon"]}')],
            'a scalar in days' => [self::doc('{"days": "mon", "times": ["09:00"]}')],
            'a scalar in months' => [self::doc('{"months": 2, "times": ["09:00"]}')],
            'month 13' => [self::doc('{"months": [13], "times": ["09:00"]}')],
            'duplicate months' => [self::doc('{"months": [2, 2], "times": ["09:00"]}')],
            'day of month zero' => [self::doc('{"days": [0], "times": ["09:00"]}')],
            'empty days' => [self::doc('{"days": [], "times": ["09:00"]}')],
            'an empty-string day atom' => [self::doc('{"days": [""], "times": ["09:00"]}')],
            'a whitespace-only day atom' => [self::doc('{"days": ["   "], "times": ["09:00"]}')],
            'duplicate integers in days' => [self::doc('{"days": [25, 25], "times": ["09:00"]}')],
            'duplicate ordinal tuples in days' => [self::doc('{"days": [["3rd", "mon"], ["3rd", "mon"]], "times": ["09:00"]}')],
            'an ordinal word outside a tuple' => [self::doc('{"days": ["3rd", "mon"], "times": ["09:00"]}')],
            'a reversed ordinal tuple' => [self::doc('{"days": [["mon", "3rd"]], "times": ["09:00"]}')],
            'not inside days' => [self::doc('{"days": ["not", "holiday"], "times": ["09:00"]}')],
            'a date literal inside days' => [self::doc('{"days": ["2026-10-01"], "times": ["09:00"]}')],
            'both times and allday' => [self::doc('{"times": ["09:00"], "allday": true}')],
            'neither times nor allday' => [self::doc('{"days": ["mon"]}')],
            'allday false' => [self::doc('{"allday": false}')],
            'a time without zero padding' => [self::doc('{"times": ["9:00"]}')],
            'a time with a trailing newline' => [self::doc('{"times": ["09:00\\n"]}')],
            'a window end with a trailing newline' => [self::doc('{"times": {"every": [1, "hour"], "between": ["09:00", "18:00\\n"]}}')],
            '24:00 as a fixed time' => [self::doc('{"times": ["24:00"]}')],
            'duplicate fixed times' => [self::doc('{"times": ["09:00", "09:00"]}')],
            'empty times' => [self::doc('{"times": []}')],
            'a count of zero in every' => [self::doc('{"times": {"every": [0, "hour"]}}')],
            'exceeding the hour maximum of every' => [self::doc('{"times": {"every": [25, "hour"]}}')],
            'exceeding the minute maximum of every' => [self::doc('{"times": {"every": [1441, "minute"]}}')],
            'exceeding the second maximum of every' => [self::doc('{"times": {"every": [86401, "second"]}}')],
            'a plural unit word' => [self::doc('{"times": {"every": [2, "hours"]}}')],
            'an unknown key in the grid' => [self::doc('{"times": {"every": [1, "hour"], "window": ["08:00", "20:00"]}}')],
            'a user-defined name in between' => [self::doc('{"times": {"every": [1, "hour"], "between": "afternoon"}}')],
            'a four-element shift' => [self::doc('{"days": [25], "shift": ["prev", "or_same", "business_day", "fri"], "times": ["09:00"]}')],
            'same in if' => [self::doc('{"days": ["mon"], "if": ["same", "holiday"], "times": ["09:00"]}')],
            'an unknown definitions key' => [self::doc('{"times": ["09:00"]}', definitions: '{"holiday": []}')],
            'a reserved word as a custom name' => [self::doc('{"times": ["09:00"]}', definitions: '{"custom": {"holiday": ["2026-01-01"]}}')],
            'a whitespace-only custom name' => [self::doc('{"days": ["   "], "times": ["09:00"]}', definitions: '{"custom": {"   ": ["2026-01-01"]}}')],
            'a date-shaped custom name' => [self::doc('{"times": ["09:00"]}', definitions: '{"custom": {"2026-01-01": ["2026-01-01"]}}')],
            'a date with a trailing newline' => [self::doc('{"times": ["09:00"]}', definitions: '{"holidays": ["2026-01-01\\n"]}')],
            'a duplicate date in a date set' => [self::doc('{"times": ["09:00"]}', definitions: '{"holidays": ["2026-01-01", "2026-01-01"]}')],
            'a whitespace-only resolver name' => [self::doc('{"times": ["09:00"]}', definitions: '{"holidays": "   "}')],
            'a single date string as a custom value' => [self::doc('{"times": ["09:00"]}', definitions: '{"custom": {"anniversary": "2026-10-01"}}')],
            'an invalid day name in workweek' => [self::doc('{"times": ["09:00"]}', definitions: '{"workweek": ["monday"]}')],
            'the same window twice in business_hours' => [self::doc(
                '{"times": ["09:00"]}',
                definitions: '{"business_hours": [["09:00", "12:00"], ["09:00", "12:00"]]}',
            )],
            'a T separator in from' => [self::doc('{"from": "2026-07-14T00:00", "times": ["09:00"]}')],
            'a date-only from' => [self::doc('{"from": "2026-07-14", "times": ["09:00"]}')],
            'a from with seconds' => [self::doc('{"from": "2026-07-14 00:00:00", "times": ["09:00"]}')],
            '24:00 in until' => [self::doc('{"until": "2026-07-14 24:00", "times": ["09:00"]}')],
            'a from without zero padding' => [self::doc('{"from": "2026-7-14 09:00", "times": ["09:00"]}')],
            'a cycle tuple with the unit omitted' => [self::doc('{"from": "2026-07-14 00:00", "days": [["every", 2]], "times": ["09:00"]}')],
            'a cycle tuple with the unit hour' => [self::doc('{"from": "2026-07-14 00:00", "days": [["every", 2, "hour"]], "times": ["09:00"]}')],
            'a cycle count of zero' => [self::doc('{"from": "2026-07-14 00:00", "days": [["every", 0, "day"]], "times": ["09:00"]}')],
            'a cycle tuple in shift' => [self::doc(
                '{"from": "2026-07-14 00:00", "days": [25], "shift": ["prev", ["every", 2, "day"]], "times": ["09:00"]}',
            )],
            'a cycle tuple in if' => [self::doc(
                '{"from": "2026-07-14 00:00", "days": ["mon"], "if": [["every", 2, "day"]], "times": ["09:00"]}',
            )],
            'the unit day in the interval every' => [self::doc('{"from": "2026-07-14 00:00", "every": [2, "day"]}')],
            'a count of zero in the interval every' => [self::doc('{"from": "2026-07-14 00:00", "every": [0, "hour"]}')],
            'the interval every combined with times' => [self::doc('{"from": "2026-07-14 00:00", "every": [36, "hour"], "times": ["09:00"]}')],
            'the interval every combined with days' => [self::doc('{"from": "2026-07-14 00:00", "every": [36, "hour"], "days": ["mon"]}')],
            'the interval every without from' => [self::doc('{"every": [36, "hour"]}')],
            'the reserved word day as a custom name' => [self::doc('{"times": ["09:00"]}', definitions: '{"custom": {"day": ["2026-01-01"]}}')],
            'the reserved word from as a custom name' => [self::doc('{"times": ["09:00"]}', definitions: '{"custom": {"from": ["2026-01-01"]}}')],
        ];
    }

    /**
     * Constraints the schema (the authority on the syntax) cannot express;
     * the implementation validates them at parse time.
     *
     * @return array<string, list<string>>
     */
    public static function semanticInvalidDocuments(): array
    {
        return [
            'a reference to an undefined custom name' => [self::doc('{"days": ["founding-day"], "times": ["09:00"]}')],
            'holiday without the holidays definition' => [self::doc('{"days": ["holiday"], "times": ["09:00"]}')],
            'business_day short of the three layers' => [self::doc(
                '{"days": ["business_day"], "times": ["09:00"]}',
                definitions: '{"holidays": []}',
            )],
            'business_hour without the business_hours definition' => [self::doc(
                '{"times": {"every": [1, "hour"], "between": "business_hour"}}',
            )],
            'an unregistered resolver name' => [self::doc('{"times": ["09:00"]}', definitions: '{"holidays": "unknown-resolver"}')],
            'a timezone that does not exist' => ['{"version": 1, "timezone": "Asia/Edo", "schedules": [{"times": ["09:00"]}]}'],
            'a fixed-offset timezone' => ['{"version": 1, "timezone": "+09:00", "schedules": [{"times": ["09:00"]}]}'],
            'a timezone abbreviation' => ['{"version": 1, "timezone": "JST", "schedules": [{"times": ["09:00"]}]}'],
            'a window crossing midnight' => [self::doc('{"times": {"every": [1, "hour"], "between": ["20:00", "08:00"]}}')],
            'a definition with a date that does not exist' => [self::doc('{"times": ["09:00"]}', definitions: '{"custom": {"anniversary": ["2026-02-30"]}}')],
            'a definition with overlapping windows' => [self::doc(
                '{"times": ["09:00"]}',
                definitions: '{"business_hours": [["09:00", "13:00"], ["12:00", "18:00"]]}',
            )],
            'from and until at the same instant' => [self::doc(
                '{"from": "2026-08-01 00:00", "until": "2026-08-01 00:00", "times": ["09:00"]}',
            )],
            'from after until' => [self::doc(
                '{"from": "2026-09-01 00:00", "until": "2026-08-01 00:00", "times": ["09:00"]}',
            )],
            'a cycle atom without from' => [self::doc('{"days": [["every", 2, "day"]], "times": ["09:00"]}')],
            'a from whose date does not exist' => [self::doc('{"from": "2026-02-30 00:00", "times": ["09:00"]}')],
        ];
    }

    // ---- helpers ----

    private static function doc(string $scheduleJson, ?string $definitions = null): string
    {
        $definitionsPart = $definitions === null ? '' : ', "definitions": ' . $definitions;

        return '{"version": 1, "timezone": "Asia/Tokyo"' . $definitionsPart . ', "schedules": [' . $scheduleJson . ']}';
    }

    private function parser(): YrnkParser
    {
        return new YrnkParser(resolvers: ['yasumi-jp' => static fn(): array => ['2026-01-01']]);
    }

    private function schemaAccepts(string $json): bool
    {
        $validator = new Validator();
        $validator->resolver()?->registerFile(self::SCHEMA_ID, dirname(__DIR__, 2) . '/schema/yarunoka.schema.json');

        return $validator->validate(json_decode($json), self::SCHEMA_ID)->isValid();
    }
}
