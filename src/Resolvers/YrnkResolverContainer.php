<?php

namespace Yarunoka\Resolvers;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Internal\Resolvers\YasumiProviders;

/**
 * What the host binds the document's resolver names to. A calendar carries
 * one, so a binding is handed over once and reaches both the reference
 * validation and the evaluation.
 *
 * Holding the bindings in a container rather than a plain array is what
 * makes a duplicate name an error: a PHP array literal keyed by name keeps
 * the last of two entries and says nothing, and every place that accepts
 * bindings would otherwise have to check for itself.
 */
final class YrnkResolverContainer
{
    /** @var array<string, YrnkResolverInterface> */
    private array $bindings;

    public function __construct()
    {
        // The names yasumi supplies are bound before anything else, so a
        // host adding one of them collides like any other duplicate.
        $this->bindings = YasumiProviders::available();
    }

    /**
     * Binds a name to what resolves it. The name is the document's, so it
     * is held to the same rules the document is: not blank, and not shaped
     * like a date literal (the two forms a date-list position accepts are
     * told apart by shape).
     */
    public function add(string $name, YrnkResolverInterface $resolver): void
    {
        if (preg_match('/\S/u', $name) !== 1) {
            throw new InvalidValueException('Resolver name cannot be empty or whitespace only');
        }

        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $name) === 1) {
            throw new InvalidValueException("A date-shaped string cannot be used as a resolver name: {$name}");
        }

        if (isset($this->bindings[$name])) {
            throw new InvalidValueException("A resolver is already bound to this name: {$name}");
        }

        $this->bindings[$name] = $resolver;
    }

    public function has(string $name): bool
    {
        return isset($this->bindings[$name]);
    }

    public function get(string $name): ?YrnkResolverInterface
    {
        return $this->bindings[$name] ?? null;
    }
}
