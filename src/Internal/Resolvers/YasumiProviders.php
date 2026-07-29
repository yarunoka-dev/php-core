<?php

namespace Yarunoka\Internal\Resolvers;

use Yarunoka\Resolvers\YrnkResolverInterface;
use Yasumi\Yasumi;

/**
 * The names yasumi supplies, by the convention yasumi-{provider}. Nothing
 * when the library is absent, so a document that spells one of these names
 * reads as unbound like any other.
 *
 * These are ordinary bindings, seeded before the host adds its own: a name
 * yasumi already answers for cannot be bound a second time, the same as
 * any duplicate. Which is why the convenience is expressed as a set of
 * names to start from, rather than a fallback consulted after the host.
 *
 * @internal
 */
final class YasumiProviders
{
    private const string PREFIX = 'yasumi-';

    /**
     * @return array<string, YrnkResolverInterface>
     */
    public static function available(): array
    {
        if (! class_exists(Yasumi::class)) {
            return [];
        }

        $bindings = [];

        foreach (Yasumi::getProviders() as $provider) {
            $bindings[self::PREFIX . $provider] = new YasumiHolidaysResolver($provider);
        }

        return $bindings;
    }
}
