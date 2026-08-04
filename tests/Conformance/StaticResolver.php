<?php

namespace Yarunoka\Tests\Conformance;

use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\YrnkDate;

/**
 * The pass-through resolver a kit binding becomes: it answers the bound
 * list as-is, whatever range is asked. The kit's cases author the lists
 * to cover what their queries reach, so cutting to the range is left to
 * the evaluating side like any other resolver answer.
 */
final class StaticResolver implements YrnkResolverInterface
{
    /**
     * @param  list<string>  $dates
     */
    public function __construct(private readonly array $dates) {}

    public function resolve(YrnkDate $from, YrnkDate $through): array
    {
        return $this->dates;
    }
}
