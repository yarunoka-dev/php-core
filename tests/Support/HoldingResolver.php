<?php

namespace Yarunoka\Tests\Support;

use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\YrnkDate;

/**
 * A test resolver that computes its list once and hands the same one back
 * on every later call — the shape a caller reaches for when it does not
 * want a lookup per question. Counts the computations, not the calls.
 */
final class HoldingResolver implements YrnkResolverInterface
{
    public int $computations = 0;

    /** @var list<string>|null */
    private ?array $held = null;

    /**
     * @param  list<string>  $dates
     */
    public function __construct(private readonly array $dates) {}

    public function resolve(YrnkDate $from, YrnkDate $to): array
    {
        if ($this->held === null) {
            $this->computations++;
            $this->held = $this->dates;
        }

        return $this->held;
    }
}
