<?php

namespace Yarunoka\Internal\Vocabulary;

/**
 * The unit word of `every`. Singular form only — in a machine-read document,
 * "either is fine" is nothing but noise for diffs, validation, and
 * cross-implementation compatibility.
 *
 * @internal
 */
enum TimeUnit: string
{
    case Hour = 'hour';
    case Minute = 'minute';
    case Second = 'second';

    public function seconds(): int
    {
        return match ($this) {
            self::Hour => 3600,
            self::Minute => 60,
            self::Second => 1,
        };
    }

    public function maximumAmount(): int
    {
        return intdiv(86400, $this->seconds());
    }

    /**
     * The largest interval-every count whose second point stays inside
     * the date domain when `from` sits at its lower end (the 1.1 bound;
     * the grid's one-day cap above does not apply to a from-anchored
     * sequence).
     */
    public function sequenceMaximumCount(): int
    {
        return match ($this) {
            self::Hour => 87_649_415,
            self::Minute => 5_258_964_959,
            self::Second => 315_537_897_599,
        };
    }
}
