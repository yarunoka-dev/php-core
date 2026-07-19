<?php

namespace Yarunoka\Internal\Builder;

use Yarunoka\Expression\IfGuard;

/**
 * The mirror image of IfGuardParser. If node → RawIf.
 *
 * @internal
 */
final class IfGuardBuilder
{
    /**
     * @return list<int|string|list<int|string>>
     */
    public static function build(IfGuard $guard): array
    {
        $raw = [];

        if ($guard->direction !== null) {
            $raw[] = $guard->direction->value;
        }

        if ($guard->negated) {
            $raw[] = 'not';
        }

        $raw[] = DayAtomBuilder::build($guard->condition);

        return $raw;
    }
}
