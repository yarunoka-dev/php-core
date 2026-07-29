<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Schedule\DateSetRef;
use Yarunoka\Schedule\DayAtomInterface;
use Yarunoka\Schedule\LastDayOfMonth;
use Yarunoka\Schedule\MonthDay;
use Yarunoka\Schedule\OrdinalWeekday;
use Yarunoka\Schedule\Weekday;
use Yarunoka\Internal\Vocabulary\CalendarWord;
use Yarunoka\Vocabulary\YrnkDayName;
use Yarunoka\Internal\Vocabulary\Ordinal;

/**
 * The parser for day expression atoms (RawDayAtom). Branches mechanically
 * on the type (int / string / two-element array) and fails loudly on
 * misplacements. A name becomes a DateSetRef holding the name
 * as-is (validating that the referent exists is the job of the holder of
 * the definitions).
 *
 * @internal
 */
final class DayAtomParser
{
    /** Structural words of shift / if. Their appearance in an atom position gets a dedicated error */
    private const array MODIFIER_WORDS = ['not', 'prev', 'next', 'or_same'];

    public static function parse(mixed $raw): DayAtomInterface
    {
        if (is_int($raw)) {
            if ($raw < 1 || $raw > 31) {
                throw new InvalidYrnkException("Day of month must be between 1 and 31: {$raw}");
            }

            return new MonthDay($raw);
        }

        if (is_string($raw)) {
            return self::parseWord($raw);
        }

        if (is_array($raw)) {
            return self::parseOrdinalTuple($raw);
        }

        $given = get_debug_type($raw);

        throw new InvalidYrnkException("Cannot interpret as a day expression atom ({$given})");
    }

    private static function parseWord(string $word): DayAtomInterface
    {
        if ($word === '') {
            throw new InvalidYrnkException('Day expression atom cannot be an empty string');
        }

        $dayName = YrnkDayName::tryFrom($word);

        if ($dayName !== null) {
            return new Weekday($dayName);
        }

        $calendarWord = CalendarWord::tryFrom($word);

        if ($calendarWord !== null) {
            return $calendarWord;
        }

        if ($word === 'last_day_of_month') {
            return new LastDayOfMonth();
        }

        if (Ordinal::tryFrom($word) !== null) {
            throw new InvalidYrnkException(
                "An ordinal word is usable only inside a tuple: write \"{$word}\" as [[\"{$word}\", \"mon\"]]",
            );
        }

        if (in_array($word, self::MODIFIER_WORDS, true)) {
            throw new InvalidYrnkException("\"{$word}\" is not usable as a day expression atom (it is a structural word of shift / if)");
        }

        if ($word === 'business_hour') {
            throw new InvalidYrnkException('business_hour is window vocabulary (use it in between)');
        }

        if (preg_match('/\A(\d+|\d{4}-\d{2}-\d{2}|\d{2}:\d{2})\z/', $word) === 1) {
            throw new InvalidYrnkException(
                "A literal shape cannot be written directly in days: {$word} (give the dates a name under date_sets and refer to it)",
            );
        }

        // Whatever is left is a name, and it is held to what every name is
        // held to — the vocabulary above has already taken the words that
        // read as something else.
        Name::ensureUsable($word);

        return new DateSetRef($word);
    }

    /**
     * @param  array<mixed>  $raw
     */
    private static function parseOrdinalTuple(array $raw): OrdinalWeekday
    {
        if (($raw[0] ?? null) === 'every') {
            throw new InvalidYrnkException('["every", N, "day"] is allowed only in the days enumeration (not in shift / if)');
        }

        if (! array_is_list($raw) || count($raw) !== 2 || ! is_string($raw[0]) || ! is_string($raw[1])) {
            throw new InvalidYrnkException('An ordinal tuple must be the two elements [ordinal word, day name]');
        }

        $ordinal = Ordinal::tryFrom($raw[0]);

        if ($ordinal === null) {
            throw new InvalidYrnkException("Ordinal word must be one of 1st through 5th or last: {$raw[0]}");
        }

        $dayName = YrnkDayName::tryFrom($raw[1]);

        if ($dayName === null) {
            throw new InvalidYrnkException("Day name must be mon through sun: {$raw[1]}");
        }

        return new OrdinalWeekday($ordinal, $dayName);
    }
}
