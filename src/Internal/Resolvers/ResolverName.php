<?php

namespace Yarunoka\Internal\Resolvers;

use Yarunoka\Exceptions\InvalidValueException;

/**
 * The rules a resolver name is held to, wherever one is written: in a
 * definition that names its source, and in the binding that answers for
 * it. Both sides check the same thing, so a name that can be bound is a
 * name that can be written.
 *
 * A date-list position tells its two forms apart by shape, which is why a
 * date-shaped name cannot be one: it would read as a date list of one.
 *
 * @internal
 */
final class ResolverName
{
    public static function ensureUsable(string $name): void
    {
        if (preg_match('/\S/u', $name) !== 1) {
            throw new InvalidValueException('Resolver name cannot be empty or whitespace only');
        }

        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $name) === 1) {
            throw new InvalidValueException("A date-shaped string cannot be used as a resolver name: {$name}");
        }
    }
}
