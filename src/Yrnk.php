<?php

namespace Yarunoka;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\UnsupportedVersionException;
use Yarunoka\Internal\Parser\Name;
use DateTimeZone;

/**
 * The root of the typed tree of a Yrnk document. The unit of exchange
 * between the DSL and objects — the output of YrnkParser and the input of
 * YrnkBuilder. Not something an application runtime carries around (in an
 * execution context, use YrnkEvaluator + YrnkSchedule).
 *
 * The declared names ride here rather than on the calendar: they are what
 * the whole document leaves to its host, and the names they cover are
 * written on both sides of the document (a calendar definition and the
 * days axis of a schedule).
 */
final readonly class Yrnk
{
    public const string SUPPORTED_VERSION = '1.0';

    /** @var non-empty-list<YrnkSchedule> */
    public array $schedules;

    /**
     * @param  list<YrnkSchedule>  $schedules  Unvalidated input. An empty list violates the invariants
     * @param  list<string>  $resolvers  The names this document leaves to its host. Empty means it leaves none
     */
    public function __construct(
        /** @internal */
        public string $version,
        public DateTimeZone $timezone,
        public YrnkCalendar $calendar,
        array $schedules,
        public array $resolvers = [],
    ) {
        // The spec requires rejecting a declared version this
        // implementation does not know rather than interpreting it.
        if ($version !== self::SUPPORTED_VERSION) {
            throw new UnsupportedVersionException(
                sprintf('This implementation supports version %s only: %s', self::SUPPORTED_VERSION, $version),
            );
        }

        // PHP's DateTimeZone also carries fixed offsets and abbreviations,
        // but the spec limits timezone to IANA tz database names, so
        // membership in the identifier list is checked here. Backward
        // links (Japan, US/Eastern) are tz database entries and pass.
        if (! in_array($timezone->getName(), DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidValueException(
                "timezone must be an IANA Time Zone Database name (a fixed offset cannot be written): {$timezone->getName()}",
            );
        }

        if ($schedules === []) {
            throw new InvalidValueException('schedules cannot be empty');
        }

        $seen = [];

        foreach ($resolvers as $name) {
            $problem = Name::problemWith($name);

            if ($problem !== null) {
                throw new InvalidValueException($problem);
            }

            if (isset($seen[$name])) {
                throw new InvalidValueException("Duplicate declared name: {$name}");
            }

            $seen[$name] = true;
        }

        $this->schedules = $schedules;
    }
}
