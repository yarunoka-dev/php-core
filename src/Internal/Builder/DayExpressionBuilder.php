<?php

namespace Yarunoka\Internal\Builder;

use Yarunoka\Schedule\DayAtomInterface;
use Yarunoka\Schedule\DayExpression;

/**
 * The mirror image of DayExpressionParser. Day expression node →
 * RawDayExpression.
 *
 * @internal
 */
final class DayExpressionBuilder
{
    /**
     * @return list<int|string|list<int|string>>
     */
    public static function build(DayExpression $expression): array
    {
        return array_map(
            static fn(DayAtomInterface $atom): int|string|array => DayAtomBuilder::build($atom),
            $expression->atoms,
        );
    }
}
