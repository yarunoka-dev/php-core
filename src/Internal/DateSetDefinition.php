<?php

namespace Yarunoka\Internal;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\Time\LocalDate;
use Closure;

/**
 * The shared implementation of a date set definition (a fixed list | a
 * resolver name reference | a deferred closure). The public types with
 * meaning (Holidays / BusinessHolidays / BusinessDays / CustomDefinition)
 * use this. A trait so that the types stay separate while the
 * implementation is shared; the public contract lives on each class.
 *
 * @internal
 */
trait DateSetDefinition
{
    /** @var list<LocalDate>|null */
    public readonly ?array $dates;

    public readonly ?string $resolver;

    public readonly ?Closure $closure;

    /**
     * @param  list<LocalDate>|null  $dates
     */
    private function __construct(?array $dates, ?string $resolver, ?Closure $closure)
    {
        $this->dates = $dates;
        $this->resolver = $resolver;
        $this->closure = $closure;
    }

    /**
     * A fixed date list. Strings are validated as zero-padded YYYY-MM-DD.
     *
     * @param  list<LocalDate|string>  $dates
     */
    public static function ofDates(array $dates): self
    {
        $parsed = array_map(
            static fn(LocalDate|string $date): LocalDate => $date instanceof LocalDate
                ? $date
                : LocalDate::fromString($date),
            $dates,
        );
        $seen = [];

        foreach ($parsed as $date) {
            $key = $date->toString();

            if (isset($seen[$key])) {
                throw new InvalidValueException("Duplicate date in date list: {$key}");
            }

            $seen[$key] = true;
        }

        return new self($parsed, null, null);
    }

    /**
     * A resolver name reference. The actual dates are resolved by a
     * resolver registered with the Parser / YrnkEvaluator.
     */
    public static function byResolver(string $name): self
    {
        if (preg_match('/\\S/u', $name) !== 1) {
            throw new InvalidValueException('Resolver name cannot be empty or whitespace only');
        }

        // Date literals and resolver names are distinguished by shape, so
        // a date-shaped name is not allowed.
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $name) === 1) {
            throw new InvalidValueException("A date-shaped string cannot be used as a resolver name: {$name}");
        }

        return new self(null, $name, null);
    }

    /**
     * A deferred list (not writable in the DSL; only when composing in
     * PHP). An instance of the resolver contract is held wrapped in a
     * Closure.
     */
    public static function deferred(Closure|YrnkResolverInterface $resolve): self
    {
        if ($resolve instanceof YrnkResolverInterface) {
            $resolve = static fn(): array => $resolve->resolve();
        }

        return new self(null, null, $resolve);
    }
}
