<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\Shift;
use Yarunoka\Vocabulary\Direction;

/**
 * The parser for shift (RawShift). [direction, landing condition] |
 * [direction, "or_same", landing condition].
 *
 * @internal
 */
final class ShiftParser
{
    public static function parse(mixed $raw): Shift
    {
        if (! is_array($raw) || ! array_is_list($raw) || count($raw) < 2 || count($raw) > 3) {
            throw new InvalidYrnkException('shift must be [direction, landing condition] or [direction, "or_same", landing condition]');
        }

        $direction = is_string($raw[0]) ? Direction::tryFrom($raw[0]) : null;

        if ($direction === null) {
            throw new InvalidYrnkException('Direction of shift must be "prev" or "next"');
        }

        if (count($raw) === 3 && $raw[1] !== 'or_same') {
            throw new InvalidYrnkException('The three-element form of shift requires "or_same" as its second element');
        }

        return new Shift(
            direction: $direction,
            orSame: count($raw) === 3,
            condition: DayAtomParser::parse($raw[count($raw) - 1]),
        );
    }
}
