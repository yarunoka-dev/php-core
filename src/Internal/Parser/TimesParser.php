<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\BusinessHourRef;
use Yarunoka\Expression\EveryGrid;
use Yarunoka\Expression\FixedTimes;
use Yarunoka\Expression\TimesSpec;
use Yarunoka\Time\TimeOfDay;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\TimeUnit;

/**
 * The parser for times (RawTimes). Distinguishes mechanically by the
 * shape of the value: a list = an enumeration of fixed times, an object =
 * the every grid.
 *
 * @internal
 */
final class TimesParser
{
    public static function parse(mixed $raw): TimesSpec
    {
        if (! is_array($raw)) {
            throw new InvalidYrnkException('times must be a list of times or the {"every": ...} grid');
        }

        return array_is_list($raw)
            ? self::parseFixedTimes($raw)
            : self::parseGrid($raw);
    }

    /**
     * @param  list<mixed>  $raw
     */
    private static function parseFixedTimes(array $raw): FixedTimes
    {
        if ($raw === []) {
            throw new InvalidYrnkException('Times enumeration cannot be empty');
        }

        return new FixedTimes(array_map(
            static function (mixed $time): TimeOfDay {
                if (! is_string($time)) {
                    throw new InvalidYrnkException('Elements of times must be HH:MM strings');
                }

                return TimeOfDay::fromString($time);
            },
            $raw,
        ));
    }

    /**
     * @param  array<mixed>  $raw
     */
    private static function parseGrid(array $raw): EveryGrid
    {
        $unknownKeys = array_diff(array_keys($raw), ['every', 'between']);

        if ($unknownKeys !== []) {
            throw new InvalidYrnkException('The only keys allowed in the times grid are every and between: ' . implode(', ', $unknownKeys));
        }

        if (! array_key_exists('every', $raw)) {
            throw new InvalidYrnkException('The times grid requires every');
        }

        [$amount, $unit] = self::parseEvery($raw['every']);

        return new EveryGrid(
            amount: $amount,
            unit: $unit,
            between: array_key_exists('between', $raw) ? self::parseBetween($raw['between']) : null,
        );
    }

    /**
     * @return array{int, TimeUnit}
     */
    private static function parseEvery(mixed $raw): array
    {
        if (! is_array($raw) || ! array_is_list($raw) || count($raw) !== 2) {
            throw new InvalidYrnkException('every must be the two elements [count, unit]');
        }

        [$amount, $unitWord] = $raw;

        if (! is_int($amount) || $amount < 1) {
            throw new InvalidYrnkException('Count of every must be an integer of at least 1');
        }

        $unit = is_string($unitWord) ? TimeUnit::tryFrom($unitWord) : null;

        if ($unit === null) {
            $given = is_string($unitWord) ? $unitWord : get_debug_type($unitWord);

            throw new InvalidYrnkException("Unit of every must be \"hour\" | \"minute\" | \"second\" (singular): {$given}");
        }

        return [$amount, $unit];
    }

    private static function parseBetween(mixed $raw): TimeWindow|BusinessHourRef
    {
        if ($raw === 'business_hour') {
            return new BusinessHourRef();
        }

        if (is_string($raw)) {
            throw new InvalidYrnkException("The only name allowed in between is \"business_hour\": {$raw}");
        }

        if (is_array($raw) && array_is_list($raw) && count($raw) === 2
            && is_string($raw[0]) && is_string($raw[1])) {
            try {
                return TimeWindow::fromStrings($raw[0], $raw[1]);
            } catch (InvalidValueException $e) {
                throw new InvalidYrnkException($e->getMessage());
            }
        }

        throw new InvalidYrnkException('between must be an [HH:MM, HH:MM] pair or "business_hour"');
    }
}
