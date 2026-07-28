<?php

namespace Yarunoka\Expression;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Vocabulary\TimeUnit;

/**
 * The from-anchored interval sequence ({"from": ..., "every": [36,
 * "hour"]}). The points are from + k × interval (k = 0, 1, 2, …), and it
 * keeps counting across days (unlike the times clock grid there is no
 * per-day re-anchoring). The count and the unit are kept as written. The
 * count has no upper bound — the grid's one-day cap is a consequence of
 * its per-day re-anchoring semantics and does not apply to a
 * from-anchored sequence.
 */
final readonly class EverySequence implements TimesSpec
{
    public function __construct(
        public int $amount,
        public TimeUnit $unit,
    ) {
        if ($amount < 1) {
            throw new InvalidValueException("Count of every must be an integer of at least 1: {$amount}");
        }
    }

    public function stepSeconds(): int
    {
        return $this->amount * $this->unit->seconds();
    }
}
