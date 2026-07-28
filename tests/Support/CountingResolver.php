<?php

namespace Yarunoka\Tests\Support;

use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\YrnkDate;

/**
 * A test resolver that counts its calls and records the ranges it is
 * asked for. Returns the given date list as-is.
 */
final class CountingResolver implements YrnkResolverInterface
{
    public int $calls = 0;

    /** @var list<array{string, string}> */
    public array $ranges = [];

    /**
     * @param  list<string>  $dates
     */
    public function __construct(private readonly array $dates) {}

    public function resolve(YrnkDate $from, YrnkDate $to): array
    {
        $this->calls++;
        $this->ranges[] = [$from->format('Y-m-d'), $to->format('Y-m-d')];

        return $this->dates;
    }
}
