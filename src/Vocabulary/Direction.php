<?php

namespace Yarunoka\Vocabulary;

/**
 * The direction of `shift` / `if`.
 */
enum Direction: string
{
    case Prev = 'prev';
    case Next = 'next';

    /**
     * The increment for advancing one day in this direction.
     */
    public function step(): int
    {
        return match ($this) {
            self::Prev => -1,
            self::Next => 1,
        };
    }
}
