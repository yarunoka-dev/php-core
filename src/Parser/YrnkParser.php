<?php

namespace Yarunoka\Parser;

use Yarunoka\Calendar\Calendar;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Internal\Parser\CalendarParser;
use Yarunoka\Internal\ReferenceChecker;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\Yrnk;
use Yarunoka\YrnkSchedule;
use Closure;
use DateTimeZone;
use Exception;

/**
 * Parses a Yrnk document (RawYrnk) into a Yrnk. Delegates each element of
 * schedules to the ScheduleParser, and validates here what can only be
 * validated with the whole document and its definitions together —
 * resolvability of custom references, the data behind the built-in
 * vocabulary, and resolver names.
 */
final class YrnkParser
{
    private const array KNOWN_KEYS = ['version', 'timezone', 'calendar', 'schedules'];

    /**
     * @param  array<string, (Closure(): list<string>)|YrnkResolverInterface>  $resolvers  Resolver name → date list supplier (a function | the resolver contract)
     */
    public function __construct(
        private readonly array $resolvers = [],
        private readonly ScheduleParser $scheduleParser = new ScheduleParser(),
    ) {}

    /**
     * @param  string|array<mixed>  $input  A JSON string or a decoded array
     */
    public function parse(string|array $input): Yrnk
    {
        if (is_string($input)) {
            $decoded = json_decode($input, associative: true);

            if (! is_array($decoded)) {
                throw new InvalidYrnkException('A Yrnk document must be a JSON object');
            }

            $input = $decoded;
        }

        $unknownKeys = array_diff(array_keys($input), self::KNOWN_KEYS);

        if ($unknownKeys !== []) {
            throw new InvalidYrnkException('Unknown keys in the document: ' . implode(', ', $unknownKeys));
        }

        $calendar = CalendarParser::parse($input['calendar'] ?? []);

        try {
            $document = new Yrnk(
                version: $this->parseVersion($input),
                timezone: $this->parseTimezone($input),
                calendar: $calendar,
                schedules: $this->parseSchedules($input),
            );
        } catch (InvalidValueException $e) {
            throw new InvalidYrnkException($e->getMessage());
        }

        ReferenceChecker::ensureResolvable($document->schedules, $calendar, $this->resolvers);

        return $document;
    }

    /**
     * @param  array<mixed>  $input
     */
    private function parseVersion(array $input): string
    {
        if (! array_key_exists('version', $input)) {
            throw new InvalidYrnkException('version is required');
        }

        if (! is_string($input['version'])) {
            throw new InvalidYrnkException('version must be an "x.y" string (e.g. "1.0")');
        }

        return $input['version'];
    }

    /**
     * @param  array<mixed>  $input
     */
    private function parseTimezone(array $input): DateTimeZone
    {
        if (! array_key_exists('timezone', $input) || ! is_string($input['timezone'])) {
            throw new InvalidYrnkException('timezone is required (e.g. "Asia/Tokyo")');
        }

        try {
            return new DateTimeZone($input['timezone']);
        } catch (Exception) {
            throw new InvalidYrnkException("Unknown timezone: {$input['timezone']}");
        }
    }

    /**
     * @param  array<mixed>  $input
     * @return list<YrnkSchedule>
     */
    private function parseSchedules(array $input): array
    {
        if (! array_key_exists('schedules', $input)) {
            throw new InvalidYrnkException('schedules is required');
        }

        $raw = $input['schedules'];

        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new InvalidYrnkException('schedules must be a list of schedules (a bare object cannot be written)');
        }

        return array_map(
            function (mixed $schedule): YrnkSchedule {
                if (! is_array($schedule)) {
                    throw new InvalidYrnkException('Elements of schedules must be objects');
                }

                return $this->scheduleParser->parse($schedule);
            },
            $raw,
        );
    }
}
