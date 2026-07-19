<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\EverySequence;
use Yarunoka\Vocabulary\TimeUnit;

/**
 * The parser for the interval every directly on a schedule ({"every":
 * [36, "hour"]}). Unlike the times grid the count has no upper bound (a
 * from-anchored sequence keeps counting across days, so a one-day cap
 * would be meaningless).
 *
 * @internal
 */
final class EverySequenceParser
{
    public static function parse(mixed $raw): EverySequence
    {
        if (! is_array($raw) || ! array_is_list($raw) || count($raw) !== 2) {
            throw new InvalidYrnkException('every must be the two elements [count, unit]');
        }

        [$amount, $unitWord] = $raw;

        if (! is_int($amount) || $amount < 1) {
            throw new InvalidYrnkException('Count of every must be an integer of at least 1');
        }

        if ($unitWord === 'day') {
            throw new InvalidYrnkException('The interval every does not take "day" (write whole-day cycles as ["every", N, "day"] in days)');
        }

        $unit = is_string($unitWord) ? TimeUnit::tryFrom($unitWord) : null;

        if ($unit === null) {
            $given = is_string($unitWord) ? $unitWord : get_debug_type($unitWord);

            throw new InvalidYrnkException("Unit of every must be \"hour\" | \"minute\" | \"second\" (singular): {$given}");
        }

        return new EverySequence($amount, $unit);
    }
}
