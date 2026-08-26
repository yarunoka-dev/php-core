<?php

namespace Yarunoka;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\UnsupportedVersionException;
use Yarunoka\Internal\Annotation;
use Yarunoka\Internal\Parser\Name;
use Yarunoka\Schedule\DayCycle;
use Yarunoka\Schedule\EverySequence;
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
    /**
     * The spec versions this implementation knows, in the order they were
     * released. A document declaring any other version is rejected rather
     * than interpreted. 1.0 is deprecated by the spec but stays accepted:
     * the acceptance obligation ends only at a major raise.
     *
     * @var non-empty-list<string>
     */
    public const array SUPPORTED_VERSIONS = ['1.0', '1.1'];

    /** @var non-empty-list<YrnkSchedule> */
    public array $schedules;

    /**
     * @param  list<YrnkSchedule>  $schedules  Unvalidated input. An empty list violates the invariants
     * @param  list<string>  $resolvers  The names this document leaves to its host. Empty means it leaves none
     * @param  string|null  $label  Annotation: one line saying what this document is. Inert — the language never reads it
     * @param  string|null  $description  Annotation: a longer note; LF as the only line break. Inert likewise
     */
    public function __construct(
        /** @internal */
        public string $version,
        public DateTimeZone $timezone,
        public YrnkCalendar $calendar,
        array $schedules,
        public array $resolvers = [],
        public ?string $label = null,
        public ?string $description = null,
    ) {
        // The spec requires rejecting a declared version this
        // implementation does not know rather than interpreting it.
        if (! in_array($version, self::SUPPORTED_VERSIONS, true)) {
            throw new UnsupportedVersionException(
                sprintf('This implementation supports versions %s only: %s', implode(' and ', self::SUPPORTED_VERSIONS), $version),
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

        // 1.1 bounds the counting forms by the date domain. The bound
        // binds documents declaring 1.1 (validity follows the declared
        // version); a 1.0 document keeps its unbounded counts, which the
        // closed date domain answers with the from point alone.
        if ($version !== '1.0') {
            self::ensureCountsWithinDomainBounds($schedules);
        }

        if ($this->label !== null) {
            Annotation::ensureLabel($this->label);
        }

        if ($this->description !== null) {
            Annotation::ensureDescription($this->description);
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

    /**
     * The 1.1 count bounds: for each counting form, the largest count
     * whose second point stays inside the date domain when from sits at
     * its lower end. Every over-bound count collapses to the same
     * behavior — the from point alone — so rejecting it forfeits no
     * expressiveness.
     *
     * @param  list<YrnkSchedule>  $schedules
     */
    private static function ensureCountsWithinDomainBounds(array $schedules): void
    {
        foreach ($schedules as $schedule) {
            $times = $schedule->times;

            if ($times instanceof EverySequence && $times->amount > $times->unit->sequenceMaximumCount()) {
                throw new InvalidValueException(sprintf(
                    'Count of every must be at most %d for the unit "%s": %d',
                    $times->unit->sequenceMaximumCount(),
                    $times->unit->value,
                    $times->amount,
                ));
            }

            foreach ($schedule->days->atoms ?? [] as $atom) {
                if ($atom instanceof DayCycle && $atom->intervalDays > DayCycle::MAX_COUNT) {
                    throw new InvalidValueException(sprintf(
                        'Count of ["every", N, "day"] must be at most %d: %d',
                        DayCycle::MAX_COUNT,
                        $atom->intervalDays,
                    ));
                }
            }
        }
    }
}
