<?php

namespace Yarunoka;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Expression\DayCycle;
use Yarunoka\Expression\DayExpression;
use Yarunoka\Expression\EverySequence;
use Yarunoka\Expression\IfGuard;
use Yarunoka\Expression\Shift;
use Yarunoka\Expression\TimesSpec;
use Yarunoka\Time\LocalDateTime;

/**
 * The definition corresponding 1:1 to one element of the DSL's
 * schedules[]. Carries structure only; evaluation happens by handing it
 * to YrnkEvaluator. The date axes (years / months / days) combine with
 * AND, and null means "no restriction on that axis". from / until is the
 * validity range — a boundary that clips this schedule's set of points to
 * [from, until), not a recurrence condition.
 */
final readonly class YrnkSchedule
{
    /** @var non-empty-list<int>|null */
    public ?array $years;

    /** @var non-empty-list<int>|null */
    public ?array $months;

    /**
     * @param  list<int>|null  $years  1–9999. Empty, out-of-range, or duplicated enumerations violate the invariants
     * @param  list<int>|null  $months  1–12. Likewise
     */
    public function __construct(
        public TimesSpec $times,
        ?array $years = null,
        ?array $months = null,
        public ?DayExpression $days = null,
        public ?Shift $shift = null,
        public ?IfGuard $if = null,
        public ?LocalDateTime $from = null,
        public ?LocalDateTime $until = null,
    ) {
        $this->years = self::validateAxis($years, 'years', 1, 9999);
        $this->months = self::validateAxis($months, 'months', 1, 12);
        $this->ensureRangeOrdered();
        $this->ensureCountingAnchored();
        $this->ensureSequenceStandsAlone();
    }

    private function ensureRangeOrdered(): void
    {
        if ($this->from !== null && $this->until !== null && ! $this->from->isBefore($this->until)) {
            throw new InvalidValueException('from must be earlier than until');
        }
    }

    /**
     * Vocabulary that counts (the ["every", N, "day"] atom and the
     * interval every) requires from — there is no way to start counting
     * without it.
     */
    private function ensureCountingAnchored(): void
    {
        if ($this->from !== null) {
            return;
        }

        if ($this->times instanceof EverySequence) {
            throw new InvalidValueException('The interval every requires from (there is no way to start counting without it)');
        }

        foreach ($this->days->atoms ?? [] as $atom) {
            if ($atom instanceof DayCycle) {
                throw new InvalidValueException('A schedule that uses ["every", N, "day"] requires from (there is no way to start counting without it)');
            }
        }
    }

    /**
     * The interval every is a sequence of points, not a product of
     * matching days × times, so it does not combine with the date axes
     * and modifiers.
     */
    private function ensureSequenceStandsAlone(): void
    {
        if (! $this->times instanceof EverySequence) {
            return;
        }

        if ($this->years !== null || $this->months !== null || $this->days !== null
            || $this->shift !== null || $this->if !== null) {
            throw new InvalidValueException('The interval every cannot be combined with years / months / days / shift / if');
        }
    }

    /**
     * @param  list<int>|null  $values
     * @return non-empty-list<int>|null
     */
    private static function validateAxis(?array $values, string $axis, int $min, int $max): ?array
    {
        if ($values === null) {
            return null;
        }

        if ($values === []) {
            throw new InvalidValueException("Enumeration of {$axis} cannot be empty (omit it for no restriction)");
        }

        $seen = [];

        foreach ($values as $value) {
            if ($value < $min || $value > $max) {
                throw new InvalidValueException("Value of {$axis} must be between {$min} and {$max}: {$value}");
            }

            if (isset($seen[$value])) {
                throw new InvalidValueException("Duplicate value in {$axis}: {$value}");
            }

            $seen[$value] = true;
        }

        return $values;
    }
}
