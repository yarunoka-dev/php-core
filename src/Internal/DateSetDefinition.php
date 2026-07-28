<?php

namespace Yarunoka\Internal;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\YrnkDate;
use Closure;
use DateTimeInterface;
use DateTimeZone;

/**
 * The shared implementation of a date set definition (a fixed list | a
 * resolver name reference | a deferred closure). The public types with
 * meaning (YrnkHolidays / YrnkBusinessHolidays / YrnkBusinessDays / YrnkCustomDefinition)
 * use this. A trait so that the types stay separate while the
 * implementation is shared; the public contract lives on each class.
 *
 * @internal
 */
trait DateSetDefinition
{
    /** @var list<YrnkDate>|null */
    public readonly ?array $dates;

    public readonly ?string $resolver;

    /**
     * @internal
     */
    public readonly ?Closure $closure;

    /**
     * @param  list<YrnkDate>|null  $dates
     */
    private function __construct(?array $dates, ?string $resolver, ?Closure $closure)
    {
        $this->dates = $dates;
        $this->resolver = $resolver;
        $this->closure = $closure;
    }

    /**
     * A fixed date list. A string is validated as zero-padded YYYY-MM-DD;
     * a date-time object is read as the day it spells, whatever zone it
     * happens to carry — an application keeps its holidays as dates, not
     * as instants, and converting one would silently move a day across a
     * boundary.
     *
     * Either form lands on the document's clock: a definition holds
     * wall-clock dates and declares no zone of its own, so it is handed
     * the document's when the document is read.
     *
     * @param  list<DateTimeInterface|string>  $dates
     */
    public static function ofDates(array $dates, DateTimeZone $timezone): self
    {
        $parsed = array_map(
            static fn(DateTimeInterface|string $date): YrnkDate => new YrnkDate(
                $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date,
                $timezone,
            ),
            $dates,
        );
        $seen = [];

        foreach ($parsed as $date) {
            $key = $date->format('Y-m-d');

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
            $resolve = static fn(YrnkDate $from, YrnkDate $to): array => $resolve->resolve($from, $to);
        }

        return new self(null, null, $resolve);
    }
}
