<?php

namespace Yarunoka\Resolvers;

use Yarunoka\YrnkDate;

/**
 * The contract for supplying a date set — what a resolver name in the
 * definitions resolves to. The range asked for is the range the answer has
 * to cover; dates outside it are ignored, and dates missing inside it read
 * as "not in this set". The format of the return value is validated by the
 * evaluating side.
 *
 * An implementation is called again whenever a range it has not covered
 * is reached, so it is free to compute only what it is asked for. Holding
 * results across calls is the implementation's own decision.
 */
interface YrnkResolverInterface
{
    /**
     * @return list<string> YYYY-MM-DD dates from $from through $through
     */
    public function resolve(YrnkDate $from, YrnkDate $through): array;
}
