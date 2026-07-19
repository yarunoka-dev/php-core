<?php

namespace Yarunoka\Internal\Builder;

use Yarunoka\Expression\Shift;

/**
 * The mirror image of ShiftParser. Shift node → RawShift.
 *
 * @internal
 */
final class ShiftBuilder
{
    /**
     * @return list<int|string|list<int|string>>
     */
    public static function build(Shift $shift): array
    {
        $condition = DayAtomBuilder::build($shift->condition);

        return $shift->orSame
            ? [$shift->direction->value, 'or_same', $condition]
            : [$shift->direction->value, $condition];
    }
}
