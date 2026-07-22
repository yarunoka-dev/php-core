<?php

namespace Yarunoka\Calendar;

/**
 * The definitions part of a Yrnk document. Made of the built-in
 * definitions (the five reserved keys, carrying the layer-model
 * semantics) and custom (the user's open namespace).
 *
 * null means "undefined" — a document that uses vocabulary or references
 * requiring that definition is a parse error. This is distinct from an
 * explicit empty list (the statement that there are no such days). Only
 * an undefined workweek means the default (Mon–Fri) instead.
 */
final readonly class Calendar
{
    /**
     * @param  array<string, CustomDefinition>  $custom  Key name constraints (reserved words, literal shapes) are validated by the parser
     */
    public function __construct(
        public ?Holidays $holidays = null,
        public ?BusinessHolidays $businessHolidays = null,
        public ?BusinessDays $businessDays = null,
        public ?Workweek $workweek = null,
        public ?BusinessHours $businessHours = null,
        public array $custom = [],
    ) {}
}
