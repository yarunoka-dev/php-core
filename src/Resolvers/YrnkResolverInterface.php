<?php

namespace Yarunoka\Resolvers;

/**
 * The contract for supplying a date set. Usable as the resolution target
 * of a resolver name reference in the definitions, or — alongside a
 * Closure — as the source of a deferred list. An implementation is
 * responsible for returning dates covering the range being evaluated
 * (which years to return is the implementation's own decision). The
 * format of the return value is validated by the evaluating side.
 */
interface YrnkResolverInterface
{
    /**
     * @return list<string> YYYY-MM-DD dates covering the range being evaluated
     */
    public function resolve(): array;
}
