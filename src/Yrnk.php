<?php

namespace Yarunoka;

use Yarunoka\Definitions\Definitions;
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
    public const int SUPPORTED_VERSION = 1;

    /** @var non-empty-list<YrnkSchedule> */
    public array $schedules;

    /**
     * @param  list<YrnkSchedule>  $schedules  Unvalidated input. An empty list violates the invariants
     */
    public function __construct(
        public int $version,
        public DateTimeZone $timezone,
        public Definitions $definitions,
        array $schedules,
    ) {
        if ($version !== self::SUPPORTED_VERSION) {
            throw new UnsupportedVersionException(
                sprintf('This implementation supports version %d only: %d', self::SUPPORTED_VERSION, $version),
            );
        }

        if ($schedules === []) {
            throw new InvalidValueException('schedules cannot be empty');
        }

        $this->schedules = $schedules;
    }
}
