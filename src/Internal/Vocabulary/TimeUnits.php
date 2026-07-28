<?php

namespace Yarunoka\Internal\Vocabulary;

use Yarunoka\Vocabulary\TimeUnit;

/**
 * Computations over the unit vocabulary of every. The nodes keep the
 * count and the unit as written, so folding a pair into seconds is done
 * here, at the point where the arithmetic is needed.
 *
 * @internal
 */
final class TimeUnits
{
    public static function seconds(TimeUnit $unit): int
    {
        return match ($unit) {
            TimeUnit::Hour => 3600,
            TimeUnit::Minute => 60,
            TimeUnit::Second => 1,
        };
    }

    /**
     * How many of the unit fit in one day. The bound of the clock grid,
     * whose per-day re-anchoring gives it a one-day cap.
     */
    public static function maximumAmount(TimeUnit $unit): int
    {
        return intdiv(86400, self::seconds($unit));
    }

    /**
     * The interval a written (count, unit) pair stands for.
     */
    public static function stepSeconds(int $amount, TimeUnit $unit): int
    {
        return $amount * self::seconds($unit);
    }
}
