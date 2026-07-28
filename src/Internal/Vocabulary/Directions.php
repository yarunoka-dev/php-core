<?php

namespace Yarunoka\Internal\Vocabulary;

use Yarunoka\Vocabulary\Direction;

/**
 * Computations over the direction vocabulary of shift / if.
 *
 * @internal
 */
final class Directions
{
    /**
     * The increment for advancing one day in this direction.
     */
    public static function step(Direction $direction): int
    {
        return match ($direction) {
            Direction::Prev => -1,
            Direction::Next => 1,
        };
    }
}
