<?php

namespace Yarunoka\Tests\Support;

use Yarunoka\Resolvers\YrnkResolverContainer;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\YrnkDate;

/**
 * Builds a resolver container for a test, so a case that only cares about
 * what a name resolves to does not spell out the binding calls.
 */
final class Bindings
{
    /**
     * @param  array<string, YrnkResolverInterface>  $bindings
     */
    public static function of(array $bindings): YrnkResolverContainer
    {
        $container = new YrnkResolverContainer();

        foreach ($bindings as $name => $resolver) {
            $container->add($name, $resolver);
        }

        return $container;
    }

    /**
     * A resolver that hands back the given dates whatever it is asked for.
     *
     * @param  list<string>  $dates
     */
    public static function returning(array $dates): YrnkResolverInterface
    {
        return new class ($dates) implements YrnkResolverInterface {
            /** @param list<string> $dates */
            public function __construct(private readonly array $dates) {}

            public function resolve(YrnkDate $from, YrnkDate $through): array
            {
                return $this->dates;
            }
        };
    }
}
