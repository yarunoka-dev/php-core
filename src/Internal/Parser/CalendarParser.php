<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Calendar\BusinessDays;
use Yarunoka\Calendar\BusinessHolidays;
use Yarunoka\Calendar\BusinessHours;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Calendar\CustomDefinition;
use Yarunoka\Calendar\Holidays;
use Yarunoka\Calendar\Workweek;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\DayName;

/**
 * The parser for the definitions part (RawCalendar). The top level is
 * the closed set of reserved keys (the built-in definitions); under
 * custom is the open namespace.
 *
 * @internal
 */
final class CalendarParser
{
    private const array KNOWN_KEYS = [
        'holidays', 'business_holidays', 'business_days', 'workweek', 'business_hours', 'custom',
    ];

    public static function parse(mixed $raw): Calendar
    {
        if (! is_array($raw) || ($raw !== [] && array_is_list($raw))) {
            throw new InvalidYrnkException('calendar must be an object');
        }

        $unknownKeys = array_diff(array_keys($raw), self::KNOWN_KEYS);

        if ($unknownKeys !== []) {
            throw new InvalidYrnkException('Unknown keys in the calendar: ' . implode(', ', $unknownKeys));
        }

        try {
            return new Calendar(
                holidays: array_key_exists('holidays', $raw)
                    ? self::parseDateSet($raw['holidays'], 'holidays', Holidays::class)
                    : null,
                businessHolidays: array_key_exists('business_holidays', $raw)
                    ? self::parseDateSet($raw['business_holidays'], 'business_holidays', BusinessHolidays::class)
                    : null,
                businessDays: array_key_exists('business_days', $raw)
                    ? self::parseDateSet($raw['business_days'], 'business_days', BusinessDays::class)
                    : null,
                workweek: array_key_exists('workweek', $raw) ? self::parseWorkweek($raw['workweek']) : null,
                businessHours: array_key_exists('business_hours', $raw)
                    ? self::parseBusinessHours($raw['business_hours'])
                    : null,
                custom: array_key_exists('custom', $raw) ? self::parseCustom($raw['custom']) : [],
            );
        } catch (InvalidValueException $e) {
            throw new InvalidYrnkException($e->getMessage());
        }
    }

    /**
     * @template T of Holidays|BusinessHolidays|BusinessDays|CustomDefinition
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private static function parseDateSet(mixed $raw, string $key, string $class): object
    {
        if (is_string($raw)) {
            if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $raw) === 1) {
                throw new InvalidYrnkException("{$key}: a single date is still written as a list: [\"{$raw}\"]");
            }

            // The trait-provided named constructor does not resolve to T
            // when called through class-string<T> (a false positive from a
            // phpstan limitation).
            // @phpstan-ignore return.type
            return $class::byResolver($raw);
        }

        if (is_array($raw) && array_is_list($raw)) {
            foreach ($raw as $date) {
                if (! is_string($date)) {
                    throw new InvalidYrnkException("{$key}: dates must be YYYY-MM-DD strings");
                }
            }

            /** @var list<string> $raw */
            // @phpstan-ignore return.type (the same false positive as byResolver)
            return $class::ofDates($raw);
        }

        throw new InvalidYrnkException("{$key} must be a date list or a resolver name");
    }

    private static function parseWorkweek(mixed $raw): Workweek
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new InvalidYrnkException('workweek must be a list of day names');
        }

        return new Workweek(array_map(
            static function (mixed $name): DayName {
                $dayName = is_string($name) ? DayName::tryFrom($name) : null;

                if ($dayName === null) {
                    $given = is_string($name) ? $name : get_debug_type($name);

                    throw new InvalidYrnkException("workweek: day names must be mon through sun: {$given}");
                }

                return $dayName;
            },
            $raw,
        ));
    }

    private static function parseBusinessHours(mixed $raw): BusinessHours
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new InvalidYrnkException('business_hours must be a list of [HH:MM, HH:MM] pairs');
        }

        return new BusinessHours(array_map(
            static function (mixed $pair): TimeWindow {
                if (! is_array($pair) || ! array_is_list($pair) || count($pair) !== 2
                    || ! is_string($pair[0]) || ! is_string($pair[1])) {
                    throw new InvalidYrnkException('Elements of business_hours must be [HH:MM, HH:MM] pairs');
                }

                return TimeWindow::fromStrings($pair[0], $pair[1]);
            },
            $raw,
        ));
    }

    /**
     * @return array<string, CustomDefinition>
     */
    private static function parseCustom(mixed $raw): array
    {
        if (! is_array($raw) || ($raw !== [] && array_is_list($raw))) {
            throw new InvalidYrnkException('custom must be an object of name to date list');
        }

        $custom = [];

        foreach ($raw as $name => $value) {
            // PHP turns digits-only keys of a JSON object into ints. The
            // name validation rejects them.
            $name = (string) $name;
            ReservedWords::ensureUsable($name);
            $custom[$name] = self::parseDateSet($value, "custom.{$name}", CustomDefinition::class);
        }

        return $custom;
    }
}
