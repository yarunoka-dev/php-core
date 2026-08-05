<?php

namespace Yarunoka\Internal;

use DateTimeImmutable;

/**
 * Correction of an ambiguous wall-clock reading to its first occurrence
 * (RFC 5545 §3.3.5). PHP resolves a wall time by converging on a fixed
 * point from a UTC-anchored first guess, and for a time inside a
 * fall-back overlap that lands on the pre-transition occurrence west of
 * UTC but on the post-transition one east of it. The spec admits only
 * the first occurrence, so a reading that landed after the turn-back is
 * moved onto it here; every other reading — a first occurrence, an
 * unambiguous time, a gap time already pushed forward — passes through
 * untouched.
 *
 * @internal
 */
final class FoldResolver
{
    /**
     * How far back the transition behind a reading can sit: two days,
     * comfortably above the widest fall-back in the tz database (the
     * date-line crossings of the 19th century turned clocks back by
     * about a day).
     */
    private const int TRANSITION_SLACK_SECONDS = 172800;

    /**
     * The reading moved onto the first occurrence of its wall time, or
     * the reading itself when it already stands there. An instant lies
     * on the second pass of a fold exactly when the latest transition
     * behind it turned the clock back and the instant is within the
     * fold's width of it; the first occurrence is the same wall time
     * read with the pre-transition offset, a fold width earlier.
     *
     * @template T of DateTimeImmutable
     *
     * @param  T  $reading
     * @return T
     */
    public static function firstOccurrence(DateTimeImmutable $reading): DateTimeImmutable
    {
        $timestamp = $reading->getTimestamp();
        $timezone = $reading->getTimezone();

        // getTransitions reports false (not a list) for offset- and
        // abbreviation-type zones; fold that into the empty list. Whether
        // a transition exactly at the range end is reported varies by
        // zone, and a reading on the first second of a fold's second pass
        // sits exactly on its transition — so ask one second past the
        // reading and trim what lies ahead of it.
        $transitions = $timezone->getTransitions($timestamp - self::TRANSITION_SLACK_SECONDS, $timestamp + 1) ?: [];

        if ($transitions !== [] && $transitions[count($transitions) - 1]['ts'] > $timestamp) {
            array_pop($transitions);
        }

        // The first entry only states the regime at the range start, so a
        // single entry means no transition sits behind the reading.
        if (count($transitions) < 2) {
            return $reading;
        }

        $latest = $transitions[count($transitions) - 1];
        $fold = $transitions[count($transitions) - 2]['offset'] - $latest['offset'];

        if ($fold <= 0 || $timestamp >= $latest['ts'] + $fold) {
            return $reading;
        }

        $first = (new DateTimeImmutable('@' . ($timestamp - $fold)))->setTimezone($timezone);

        return $reading::createFromInterface($first);
    }
}
