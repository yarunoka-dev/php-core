<?php

namespace Yarunoka\Internal\Resolvers;

use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\YrnkDate;
use Closure;
use Yasumi\Yasumi;

/**
 * The names a document may resolve against. What the host bound is the
 * whole of it, save for one convention: a name spelled yasumi-{provider}
 * resolves through yasumi when that library is installed and knows the
 * provider, so a document can ask for public holidays without the host
 * wiring anything.
 *
 * A host binding of the same name is used instead — what an application
 * says out loud beats what the library supplies quietly. A yasumi- name
 * whose provider does not exist, or one written while yasumi is not
 * installed, is simply not a bound name, and reads as unregistered like
 * any other.
 *
 * @internal
 */
final class ResolverRegistry
{
    private const string YASUMI_PREFIX = 'yasumi-';

    /** @var array<string, YasumiHolidaysResolver> */
    private array $yasumi = [];

    /**
     * @param  array<string, (Closure(YrnkDate, YrnkDate): list<string>)|YrnkResolverInterface>  $bound
     */
    public function __construct(private readonly array $bound) {}

    public function has(string $name): bool
    {
        return isset($this->bound[$name]) || $this->yasumiFor($name) !== null;
    }

    /**
     * @return (Closure(YrnkDate, YrnkDate): list<string>)|YrnkResolverInterface|null
     */
    public function get(string $name): Closure|YrnkResolverInterface|null
    {
        return $this->bound[$name] ?? $this->yasumiFor($name);
    }

    private function yasumiFor(string $name): ?YasumiHolidaysResolver
    {
        if (! str_starts_with($name, self::YASUMI_PREFIX) || ! class_exists(Yasumi::class)) {
            return null;
        }

        $provider = substr($name, strlen(self::YASUMI_PREFIX));

        if (! in_array($provider, Yasumi::getProviders(), true)) {
            return null;
        }

        return $this->yasumi[$name] ??= new YasumiHolidaysResolver($provider);
    }
}
