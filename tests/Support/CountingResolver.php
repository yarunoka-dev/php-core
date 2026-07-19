<?php

namespace Yarunoka\Tests\Support;

use Yarunoka\Resolvers\YrnkResolverInterface;

/**
 * A test resolver that counts its calls. Returns the given date list
 * as-is.
 */
final class CountingResolver implements YrnkResolverInterface
{
    public int $calls = 0;

    /**
     * @param  list<string>  $dates
     */
    public function __construct(private readonly array $dates) {}

    public function resolve(): array
    {
        $this->calls++;

        return $this->dates;
    }
}
