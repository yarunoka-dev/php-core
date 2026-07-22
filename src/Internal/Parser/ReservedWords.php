<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Exceptions\ReservedNameException;

/**
 * Words that cannot be registered as custom definition key names. Rejects
 * collisions with the built-in vocabulary and the structural words, and
 * shapes indistinguishable from literals (dates, times, numbers) or
 * resolver names. The namespace is structurally separated under
 * calendar.custom, so the scope is narrower than in the original
 * implementation (custom key names only).
 *
 * @internal
 */
final class ReservedWords
{
    /**
     * Deliberately duplicated content of the customName enum in
     * schema/calendar.schema.json. Agreement is verified by
     * ReservedWordsTest (public for that test).
     */
    public const array WORDS = [
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
        'version', 'timezone', 'calendar', 'schedules',
        'years', 'months', 'days', 'shift', 'if', 'times', 'allday', 'every', 'between', 'from', 'until',
        'holidays', 'business_holidays', 'business_days', 'workweek', 'business_hours', 'custom',
    ];

    public static function ensureUsable(string $name): void
    {
        if (preg_match('/\\S/u', $name) !== 1) {
            throw new ReservedNameException('Custom definition name cannot be empty or whitespace only');
        }

        if (in_array($name, self::WORDS, true)) {
            throw new ReservedNameException("\"{$name}\" is a reserved word and cannot be a custom definition name");
        }

        if (preg_match('/\A\d+\z/', $name) === 1) {
            throw new ReservedNameException("A digits-only name is indistinguishable from a day of month: {$name}");
        }

        if (preg_match('/\A\d{2}:\d{2}\z/', $name) === 1) {
            throw new ReservedNameException("A time-shaped name is not allowed: {$name}");
        }

        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $name) === 1) {
            throw new ReservedNameException("A date-shaped name is not allowed: {$name}");
        }
    }
}
