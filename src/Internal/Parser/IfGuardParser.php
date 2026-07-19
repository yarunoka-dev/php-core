<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\IfGuard;
use Yarunoka\Vocabulary\Direction;

/**
 * The parser for if (RawIf). The four forms of [direction?, "not"?,
 * condition]. prev / next / not are invalid words as day expression
 * atoms, so the first token mechanically distinguishes direction /
 * negation / condition.
 *
 * @internal
 */
final class IfGuardParser
{
    public static function parse(mixed $raw): IfGuard
    {
        if (! is_array($raw) || ! array_is_list($raw) || count($raw) < 1 || count($raw) > 3) {
            throw new InvalidYrnkException('if must be an array of one to three elements: [direction?, "not"?, condition]');
        }

        $direction = is_string($raw[0]) ? Direction::tryFrom($raw[0]) : null;
        $rest = $direction === null ? $raw : array_slice($raw, 1);

        if (count($rest) === 2) {
            if ($rest[0] !== 'not') {
                throw new InvalidYrnkException('Only "not" can precede the condition of if');
            }

            return new IfGuard($direction, negated: true, condition: DayAtomParser::parse($rest[1]));
        }

        if (count($rest) !== 1) {
            throw new InvalidYrnkException('if must be [direction?, "not"?, condition]');
        }

        return new IfGuard($direction, negated: false, condition: DayAtomParser::parse($rest[0]));
    }
}
