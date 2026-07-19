<?php

namespace Yarunoka\Expression;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\TimeUnit;

/**
 * The clock grid ({"every": [90, "minute"], "between": ...}). The count
 * and the unit are kept as written (not folded into seconds, so that
 * round-tripping is the identity). A null between means the whole day
 * [00:00, 24:00).
 */
final readonly class EveryGrid implements TimesSpec
{
    public function __construct(
        public int $amount,
        public TimeUnit $unit,
        public TimeWindow|BusinessHourRef|null $between,
    ) {
        if ($amount < 1) {
            throw new InvalidValueException("Count of every must be an integer of at least 1: {$amount}");
        }

        if ($amount > $unit->maximumAmount()) {
            throw new InvalidValueException(sprintf(
                'Count of every must be at most %2$d for the unit %1$s: %3$d',
                $unit->value,
                $unit->maximumAmount(),
                $amount,
            ));
        }
    }
}
