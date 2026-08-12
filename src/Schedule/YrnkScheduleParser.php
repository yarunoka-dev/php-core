<?php

namespace Yarunoka\Schedule;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Internal\FoldResolver;
use Yarunoka\Internal\Parser\DayExpressionParser;
use Yarunoka\Internal\Parser\EverySequenceParser;
use Yarunoka\Internal\Parser\IfGuardParser;
use Yarunoka\Internal\Parser\ShiftParser;
use Yarunoka\Internal\Parser\TimesParser;
use Yarunoka\YrnkDateTime;
use Yarunoka\YrnkSchedule;
use DateTimeZone;

/**
 * Parses one element of the DSL's schedules[] (RawSchedule) into a
 * YrnkSchedule, fully parsed and validated as such. That names are not
 * checked for resolvability here is not a limitation but a property of
 * the data: a YrnkSchedule carries no definitions (resolving names is the
 * job of YrnkParser / YrnkEvaluator).
 */
final class YrnkScheduleParser
{
    private const array KNOWN_KEYS = ['from', 'until', 'years', 'months', 'days', 'shift', 'if', 'times', 'allday', 'every', 'label', 'description'];

    /** The from / until literal: zero-padded, a single space, no seconds. */
    private const string BOUNDARY_PATTERN = '/\\A\\d{4}-\\d{2}-\\d{2} (?:[01]\\d|2[0-3]):[0-5]\\d\\z/';

    /**
     * @param  array<mixed>  $raw
     */
    public function parse(array $raw, DateTimeZone $timezone): YrnkSchedule
    {
        if ($raw !== [] && array_is_list($raw)) {
            throw new InvalidYrnkException('A schedule must be an object');
        }

        $unknownKeys = array_diff(array_keys($raw), self::KNOWN_KEYS);

        if ($unknownKeys !== []) {
            throw new InvalidYrnkException('Unknown keys in the schedule: ' . implode(', ', $unknownKeys));
        }

        try {
            [$from, $fromLiteral] = $this->parseBoundary($raw, 'from', $timezone);
            [$until, $untilLiteral] = $this->parseBoundary($raw, 'until', $timezone);

            return new YrnkSchedule(
                times: $this->parseTimeSpec($raw),
                years: $this->parseIntAxis($raw['years'] ?? null, 'years'),
                months: $this->parseIntAxis($raw['months'] ?? null, 'months'),
                days: array_key_exists('days', $raw) ? DayExpressionParser::parse($raw['days']) : null,
                shift: array_key_exists('shift', $raw) ? ShiftParser::parse($raw['shift']) : null,
                if: array_key_exists('if', $raw) ? IfGuardParser::parse($raw['if']) : null,
                from: $from,
                until: $until,
                label: self::parseAnnotation($raw, 'label'),
                description: self::parseAnnotation($raw, 'description'),
                fromLiteral: $fromLiteral,
                untilLiteral: $untilLiteral,
            );
        } catch (InvalidValueException $e) {
            // A node invariant violation is reported as a document syntax
            // error when the value came from a document.
            throw new InvalidYrnkException($e->getMessage());
        }
    }

    /**
     * The shape only — the content rules (length, control and invisible
     * characters) are the YrnkSchedule invariants, so a built schedule is
     * held to them the same as a parsed one.
     *
     * @param  array<mixed>  $raw
     */
    private static function parseAnnotation(array $raw, string $key): ?string
    {
        if (! array_key_exists($key, $raw)) {
            return null;
        }

        if (! is_string($raw[$key])) {
            $given = get_debug_type($raw[$key]);

            throw new InvalidYrnkException("{$key} must be a string: {$given}");
        }

        return $raw[$key];
    }

    /**
     * @param  array<mixed>  $raw
     */
    private function parseTimeSpec(array $raw): TimesSpecInterface
    {
        $present = array_values(array_filter(
            ['times', 'allday', 'every'],
            static fn(string $key): bool => array_key_exists($key, $raw),
        ));

        if (count($present) > 1) {
            throw new InvalidYrnkException('times / allday / every are mutually exclusive: ' . implode(', ', $present));
        }

        if ($present === []) {
            throw new InvalidYrnkException('Exactly one of times, allday, or every is required');
        }

        if ($present[0] === 'times') {
            return TimesParser::parse($raw['times']);
        }

        if ($present[0] === 'every') {
            return EverySequenceParser::parse($raw['every']);
        }

        if ($raw['allday'] !== true) {
            throw new InvalidYrnkException('allday accepts only true (omit it otherwise)');
        }

        return new AllDay();
    }

    /**
     * The boundary as the instant it resolves to, paired with its authored
     * spelling — a wall time inside a spring-forward gap resolves forward,
     * so the instant alone cannot answer the document back as written.
     *
     * @param  array<mixed>  $raw
     * @return array{?YrnkDateTime, ?string}
     */
    private function parseBoundary(array $raw, string $key, DateTimeZone $timezone): array
    {
        if (! array_key_exists($key, $raw)) {
            return [null, null];
        }

        if (! is_string($raw[$key])) {
            $given = get_debug_type($raw[$key]);

            throw new InvalidYrnkException("{$key} must be a \"YYYY-MM-DD HH:MM\" string: {$given}");
        }

        // The DSL spells a boundary without seconds. YrnkDateTime itself
        // accepts them (occurrences of the interval every land on a
        // non-zero second), so the document's grammar is checked here.
        if (preg_match(self::BOUNDARY_PATTERN, $raw[$key]) !== 1) {
            throw new InvalidYrnkException("{$key} must be a \"YYYY-MM-DD HH:MM\" string: {$raw[$key]}");
        }

        // A boundary resolves like any scheduled point, so one written in
        // the fall-back overlap stands at the first occurrence of its
        // wall time (RFC 5545 §3.3.5) — not on the reading PHP lands on.
        return [FoldResolver::firstOccurrence(new YrnkDateTime($raw[$key], $timezone)), $raw[$key]];
    }

    /**
     * @return list<int>|null
     */
    private function parseIntAxis(mixed $raw, string $axis): ?array
    {
        if ($raw === null) {
            return null;
        }

        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new InvalidYrnkException("{$axis} must be a list of integers (a scalar cannot be written)");
        }

        foreach ($raw as $value) {
            if (! is_int($value)) {
                $given = get_debug_type($value);

                throw new InvalidYrnkException("Elements of {$axis} must be integers: {$given}");
            }
        }

        /** @var list<int> $raw Range, duplicates, and non-emptiness are validated by the YrnkSchedule invariants */
        return $raw;
    }
}
