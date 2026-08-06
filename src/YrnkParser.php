<?php

namespace Yarunoka;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Exceptions\UnregisteredResolverException;
use Yarunoka\Calendar\YrnkCalendarParser;
use Yarunoka\Internal\Parser\Name;
use Yarunoka\Internal\ReferenceChecker;
use Yarunoka\Resolvers\YrnkResolverContainer;
use Yarunoka\Schedule\YrnkScheduleParser;
use DateTimeZone;
use Exception;

/**
 * Parses a Yrnk document (RawYrnk) into a Yrnk. Delegates each element of
 * schedules to the YrnkScheduleParser, and validates here what can only be
 * validated with the whole document and its definitions together —
 * resolvability of every name, the data behind the built-in
 * vocabulary, and the declarations the document makes.
 */
final class YrnkParser
{
    private const array KNOWN_KEYS = ['version', 'timezone', 'resolvers', 'calendar', 'schedules', 'label', 'description'];

    public function __construct(
        private readonly YrnkResolverContainer $resolverContainer = new YrnkResolverContainer(),
        private readonly YrnkScheduleParser $scheduleParser = new YrnkScheduleParser(),
        private readonly YrnkCalendarParser $calendarParser = new YrnkCalendarParser(),
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

        // The timezone is read first: a boundary and a calendar date are
        // points on the document's clock, so neither can be parsed
        // without it.
        $timezone = $this->parseTimezone($input);
        $calendar = $this->calendarParser->parse($input['calendar'] ?? [], $timezone, $this->resolverContainer);

        try {
            $document = new Yrnk(
                version: $this->parseVersion($input),
                timezone: $timezone,
                calendar: $calendar,
                schedules: $this->parseSchedules($input, $timezone),
                resolvers: $this->parseResolvers($input),
                label: self::parseAnnotation($input, 'label'),
                description: self::parseAnnotation($input, 'description'),
            );
        } catch (InvalidValueException $e) {
            throw new InvalidYrnkException($e->getMessage());
        }

        // Before the references are checked, so that a name the document
        // never declared is reported as that rather than as one nothing
        // resolves.
        self::ensureDeclarationsHold($document);

        ReferenceChecker::ensureResolvable($document->schedules, $calendar);

        return $document;
    }

    /**
     * The shape only — the content rules (length, control and invisible
     * characters) are the Yrnk invariants, so a built document is held to
     * them the same as a parsed one.
     *
     * @param  array<mixed>  $input
     */
    private static function parseAnnotation(array $input, string $key): ?string
    {
        if (! array_key_exists($key, $input)) {
            return null;
        }

        if (! is_string($input[$key])) {
            $given = get_debug_type($input[$key]);

            throw new InvalidYrnkException("{$key} must be a string: {$given}");
        }

        return $input[$key];
    }

    /**
     * @param  array<mixed>  $input
     * @return list<string>
     */
    private function parseResolvers(array $input): array
    {
        if (! array_key_exists('resolvers', $input)) {
            return [];
        }

        $raw = $input['resolvers'];

        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new InvalidYrnkException('resolvers must be a list of names');
        }

        if ($raw === []) {
            // "requires nothing" has one spelling, and it is the absence of
            // the key.
            throw new InvalidYrnkException('resolvers cannot be empty (a document that leaves nothing to its host omits the key)');
        }

        foreach ($raw as $name) {
            if (! is_string($name)) {
                throw new InvalidYrnkException('resolvers must be a list of names');
            }

            Name::ensureUsable($name);
        }

        /** @var list<string> */
        return $raw;
    }

    /**
     * The three things a declaration has to satisfy. Completeness is what
     * makes the list worth reading: a host prepares exactly what it says,
     * so a name used and left undefined has to be in it, and a name cannot
     * be declared and defined at once. The bindings are checked whole, so
     * a host missing several learns all of them at once instead of one per
     * attempt.
     */
    private static function ensureDeclarationsHold(Yrnk $document): void
    {
        $calendar = $document->calendar;
        $declared = array_fill_keys($document->resolvers, true);

        foreach (array_keys($calendar->dateSets) as $name) {
            if (isset($declared[$name])) {
                throw new InvalidYrnkException(
                    "A name is either defined or left to the host, never both: {$name}",
                );
            }
        }

        foreach (ReferenceChecker::namesUsedIn($document->schedules, $calendar) as $context => $name) {
            if (! isset($calendar->dateSets[$name]) && ! isset($declared[$name])) {
                throw new UndefinedNameException(
                    "Undefined name ({$context}): {$name} (define it under date_sets, or declare it under resolvers)",
                );
            }
        }

        $unbound = array_values(array_filter(
            $document->resolvers,
            static fn(string $name): bool => ! $calendar->resolverContainer->has($name),
        ));

        if ($unbound !== []) {
            throw new UnregisteredResolverException(
                'No resolver is bound to these declared names: ' . implode(', ', $unbound),
            );
        }
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
    private function parseSchedules(array $input, DateTimeZone $timezone): array
    {
        if (! array_key_exists('schedules', $input)) {
            throw new InvalidYrnkException('schedules is required');
        }

        $raw = $input['schedules'];

        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new InvalidYrnkException('schedules must be a list of schedules (a bare object cannot be written)');
        }

        $seen = [];
        $schedules = [];

        foreach ($raw as $schedule) {
            if (! is_array($schedule)) {
                throw new InvalidYrnkException('Elements of schedules must be objects');
            }

            // Compare the whole structure of the spelling, as JSON
            // Schema's uniqueItems does. JSON object equality has no
            // member order, so the members are canonicalized before the
            // comparison; list order stays part of the value.
            $key = json_encode(self::canonicalized($schedule), JSON_THROW_ON_ERROR);

            if (isset($seen[$key])) {
                throw new InvalidYrnkException('Duplicate schedule in schedules');
            }

            $seen[$key] = true;
            $schedules[] = $this->scheduleParser->parse($schedule, $timezone);
        }

        return $schedules;
    }

    /**
     * The spelling with every object's members in one fixed order, so
     * that structurally equal spellings serialize identically.
     */
    private static function canonicalized(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonicalized(...), $value);
    }
}
