<?php

namespace Yarunoka\Calendar;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Internal\Parser\Name;
use Yarunoka\Resolvers\YrnkResolverContainer;

/**
 * The definitions part of a Yrnk document. Made of the built-in
 * definitions (the five reserved keys, carrying the layer-model
 * semantics) and date_sets (the open namespace), together with what
 * the names it references resolve to.
 *
 * The bindings ride along because a definition naming a resolver is only
 * half a definition without them: whoever holds the calendar holds
 * everything needed to answer from it, and there is no second place to
 * hand them over and forget.
 *
 * A date set is written as either of the two forms the DSL accepts: the
 * date list itself, or the name of what resolves it. The name is written
 * as the name — there is no wrapper for it, because the wrapper would
 * carry nothing the string does not. Whether the name is an entry of
 * date_sets or one the host binds makes no difference to where it may be
 * written: the two share one namespace.
 *
 * null means "undefined" — a document that uses vocabulary or references
 * requiring that definition is a parse error. This is distinct from an
 * explicit empty list (the statement that there are no such days). Only
 * an undefined workweek means the default (Mon–Fri) instead.
 */
final readonly class YrnkCalendar
{
    /**
     * @param  array<string, YrnkDateSet>  $dateSets  Key name constraints (reserved words, literal shapes) are validated by the parser
     */
    public function __construct(
        public YrnkHolidays|string|null $holidays = null,
        public YrnkBusinessHolidays|string|null $businessHolidays = null,
        public YrnkBusinessDays|string|null $businessDays = null,
        public ?YrnkWorkweek $workweek = null,
        public ?YrnkBusinessHours $businessHours = null,
        public array $dateSets = [],
        public YrnkResolverContainer $resolverContainer = new YrnkResolverContainer(),
    ) {
        foreach ([$holidays, $businessHolidays, $businessDays] as $definition) {
            if (! is_string($definition)) {
                continue;
            }

            $problem = Name::problemWith($definition);

            if ($problem !== null) {
                throw new InvalidValueException($problem);
            }
        }
    }
}
