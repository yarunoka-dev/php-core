<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\DayAtom;
use Yarunoka\Expression\DayExpression;

/**
 * The parser for the day expression of days (RawDayExpression). Always an
 * array (no scalar sugar). The day-cycle tuple is writable only in days,
 * so the routing happens here rather than in the general DayAtomParser.
 *
 * @internal
 */
final class DayExpressionParser
{
    public static function parse(mixed $raw): DayExpression
    {
        if (! is_array($raw) || ! array_is_list($raw) || $raw === []) {
            throw new InvalidYrnkException('days must be a non-empty list of atoms (a scalar cannot be written)');
        }

        return new DayExpression(array_map(
            static fn (mixed $atom): DayAtom => is_array($atom) && ($atom[0] ?? null) === 'every'
                ? DayCycleParser::parse($atom)
                : DayAtomParser::parse($atom),
            $raw,
        ));
    }
}
