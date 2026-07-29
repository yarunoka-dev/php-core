<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Exceptions\ReservedNameException;

/**
 * The rules a name is held to, wherever one is written: a date_sets key,
 * a name written where a date list is expected, an atom of the days axis,
 * and the binding that answers for it. All names share one namespace, so
 * they share one rule — a name that can be bound is a name that can be
 * written.
 *
 * Rejected are collisions with the built-in vocabulary and the structural
 * words, and shapes indistinguishable from literals. The literal shapes
 * matter for reading, not for tidiness: a date-list position tells its two
 * forms apart by shape, so a date-shaped name would read as a date list of
 * one, and a digits-only name would read as a day of month in the days
 * axis.
 *
 * @internal
 */
final class Name
{
    /**
     * Deliberately duplicated content of the name enum in
     * schema/primitives.schema.json. Agreement is verified by NameTest
     * (public for that test).
     */
    public const array RESERVED_WORDS = [
        // Calendar vocabulary (days) and the window vocabulary
        'weekday', 'weekend', 'holiday', 'business_day', 'business_holiday', 'business_hour',
        // Day names
        'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun',
        // Ordinal words
        '1st', '2nd', '3rd', '4th', '5th', 'last',
        // Special days
        'last_day_of_month',
        // Structural words of shift / if
        'not', 'prev', 'next', 'or_same',
        // Unit words of every
        'hour', 'minute', 'second', 'day',
        // Structural keys of the document, schedules, and calendar
        // (they do not collide with the value namespace, but are reserved
        // to avoid confusing the reader)
        'version', 'timezone', 'resolvers', 'calendar', 'schedules',
        'years', 'months', 'days', 'shift', 'if', 'times', 'allday', 'every', 'between', 'from', 'until',
        'holidays', 'business_holidays', 'business_days', 'workweek', 'business_hours', 'date_sets',
    ];

    /**
     * Why the string cannot be a name, or null when it can. Callers throw
     * the exception their side of the boundary calls for: a name read out
     * of a document is a document error, while one handed to a node or a
     * binding is the caller's own argument.
     */
    public static function problemWith(string $name): ?string
    {
        if (preg_match('/\\S/u', $name) !== 1) {
            return 'A name cannot be empty or whitespace only';
        }

        if (in_array($name, self::RESERVED_WORDS, true)) {
            return "\"{$name}\" is a reserved word and cannot be a name";
        }

        if (preg_match('/\A\d+\z/', $name) === 1) {
            return "A digits-only name is indistinguishable from a day of month: {$name}";
        }

        if (preg_match('/\A\d{2}:\d{2}\z/', $name) === 1) {
            return "A time-shaped name is not allowed: {$name}";
        }

        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $name) === 1) {
            return "A date-shaped name is not allowed: {$name}";
        }

        return null;
    }

    public static function ensureUsable(string $name): void
    {
        $problem = self::problemWith($name);

        if ($problem !== null) {
            throw new ReservedNameException($problem);
        }
    }
}
