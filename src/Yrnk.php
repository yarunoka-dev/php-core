<?php

namespace Yarunoka;

use Yarunoka\Calendar\Calendar;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\UnsupportedVersionException;
use DateTimeZone;

/**
 * The root of the typed tree of a Yrnk document. The unit of exchange
 * between the DSL and objects — the output of YrnkParser and the input of
 * YrnkBuilder. Not something an application runtime carries around (in an
 * execution context, use YrnkEvaluator + YrnkSchedule).
 */
final readonly class Yrnk
{
    public const string SUPPORTED_VERSION = '1.0';

    /** @var non-empty-list<YrnkSchedule> */
    public array $schedules;

    /**
     * @param  list<YrnkSchedule>  $schedules  Unvalidated input. An empty list violates the invariants
     */
    public function __construct(
        public string $version,
        public DateTimeZone $timezone,
        public Calendar $calendar,
        array $schedules,
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

        $this->schedules = $schedules;
    }
}
