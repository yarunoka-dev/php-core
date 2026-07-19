<?php

namespace Yarunoka\Parser;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\AllDay;
use Yarunoka\Expression\TimesSpec;
use Yarunoka\Internal\Parser\DayExpressionParser;
use Yarunoka\Internal\Parser\EverySequenceParser;
use Yarunoka\Internal\Parser\IfGuardParser;
use Yarunoka\Internal\Parser\ShiftParser;
use Yarunoka\Internal\Parser\TimesParser;
use Yarunoka\Time\LocalDateTime;
use Yarunoka\YrnkSchedule;

/**
 * Parses one element of the DSL's schedules[] (RawSchedule) into a
 * YrnkSchedule, fully parsed and validated as such. That custom
 * references are not checked for existence here is not a limitation but
 * a property of the data: a YrnkSchedule carries no definitions
 * (resolving references is the job of YrnkParser / YrnkEvaluator).
 */
final class ScheduleParser
{
    private const array KNOWN_KEYS = ['from', 'until', 'years', 'months', 'days', 'shift', 'if', 'times', 'allday', 'every'];

    /**
     * @param  array<mixed>  $raw
     */
    public function parse(array $raw): YrnkSchedule
    {
        if ($raw !== [] && array_is_list($raw)) {
            throw new InvalidYrnkException('A schedule must be an object');
        }

        $unknownKeys = array_diff(array_keys($raw), self::KNOWN_KEYS);

        if ($unknownKeys !== []) {
            throw new InvalidYrnkException('Unknown keys in the schedule: '.implode(', ', $unknownKeys));
        }

        try {
            return new YrnkSchedule(
                times: $this->parseTimeSpec($raw),
                years: $this->parseIntAxis($raw['years'] ?? null, 'years'),
                months: $this->parseIntAxis($raw['months'] ?? null, 'months'),
                days: array_key_exists('days', $raw) ? DayExpressionParser::parse($raw['days']) : null,
                shift: array_key_exists('shift', $raw) ? ShiftParser::parse($raw['shift']) : null,
                if: array_key_exists('if', $raw) ? IfGuardParser::parse($raw['if']) : null,
                from: $this->parseBoundary($raw, 'from'),
                until: $this->parseBoundary($raw, 'until'),
            );
        } catch (InvalidValueException $e) {
            // A node invariant violation is reported as a document syntax
            // error when the value came from a document.
            throw new InvalidYrnkException($e->getMessage());
        }
    }

    /**
     * @param  array<mixed>  $raw
     */
    private function parseTimeSpec(array $raw): TimesSpec
    {
        $present = array_values(array_filter(
            ['times', 'allday', 'every'],
            static fn (string $key): bool => array_key_exists($key, $raw),
        ));

        if (count($present) > 1) {
            throw new InvalidYrnkException('times / allday / every are mutually exclusive: '.implode(', ', $present));
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

        return new AllDay;
    }

    /**
     * @param  array<mixed>  $raw
     */
    private function parseBoundary(array $raw, string $key): ?LocalDateTime
    {
        if (! array_key_exists($key, $raw)) {
            return null;
        }

        if (! is_string($raw[$key])) {
            $given = get_debug_type($raw[$key]);

            throw new InvalidYrnkException("{$key} must be a \"YYYY-MM-DD HH:MM\" string: {$given}");
        }

        return LocalDateTime::fromString($raw[$key]);
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
