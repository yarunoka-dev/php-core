<?php

namespace Yarunoka\Calendar;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Internal\Parser\ReservedWords;
use Yarunoka\Resolvers\YrnkResolverContainer;
use Yarunoka\Time\YrnkTimeWindow;
use Yarunoka\Vocabulary\YrnkDayName;
use DateTimeZone;

/**
 * The parser for the definitions part (RawCalendar). The top level is
 * the closed set of reserved keys (the built-in definitions); under
 * custom is the open namespace.
 */
final class YrnkCalendarParser
{
    private const array KNOWN_KEYS = [
        'holidays', 'business_holidays', 'business_days', 'workweek', 'business_hours', 'custom',
    ];

    public function parse(
        mixed $raw,
        DateTimeZone $timezone,
        YrnkResolverContainer $resolvers = new YrnkResolverContainer(),
    ): YrnkCalendar {
        if (! is_array($raw) || ($raw !== [] && array_is_list($raw))) {
            throw new InvalidYrnkException('calendar must be an object');
        }

        $unknownKeys = array_diff(array_keys($raw), self::KNOWN_KEYS);

        if ($unknownKeys !== []) {
            throw new InvalidYrnkException('Unknown keys in the calendar: ' . implode(', ', $unknownKeys));
        }

        try {
            return new YrnkCalendar(
                holidays: array_key_exists('holidays', $raw)
                    ? self::parseDateSet($raw['holidays'], 'holidays', YrnkHolidays::class, $timezone)
                    : null,
                businessHolidays: array_key_exists('business_holidays', $raw)
                    ? self::parseDateSet($raw['business_holidays'], 'business_holidays', YrnkBusinessHolidays::class, $timezone)
                    : null,
                businessDays: array_key_exists('business_days', $raw)
                    ? self::parseDateSet($raw['business_days'], 'business_days', YrnkBusinessDays::class, $timezone)
                    : null,
                workweek: array_key_exists('workweek', $raw) ? self::parseWorkweek($raw['workweek']) : null,
                businessHours: array_key_exists('business_hours', $raw)
                    ? self::parseBusinessHours($raw['business_hours'])
                    : null,
                custom: array_key_exists('custom', $raw) ? self::parseCustom($raw['custom'], $timezone) : [],
                resolvers: $resolvers,
            );
        } catch (InvalidValueException $e) {
            throw new InvalidYrnkException($e->getMessage());
        }
    }

    /**
     * @template T of YrnkHolidays|YrnkBusinessHolidays|YrnkBusinessDays|YrnkCustomDefinition
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private static function parseDateSet(mixed $raw, string $key, string $class, DateTimeZone $timezone): object
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
            return $class::ofDates($raw, $timezone);
        }

        throw new InvalidYrnkException("{$key} must be a date list or a resolver name");
    }

    private static function parseWorkweek(mixed $raw): YrnkWorkweek
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new InvalidYrnkException('workweek must be a list of day names');
        }

        return new YrnkWorkweek(array_map(
            static function (mixed $name): YrnkDayName {
                $dayName = is_string($name) ? YrnkDayName::tryFrom($name) : null;

                if ($dayName === null) {
                    $given = is_string($name) ? $name : get_debug_type($name);

                    throw new InvalidYrnkException("workweek: day names must be mon through sun: {$given}");
                }

                return $dayName;
            },
            $raw,
        ));
    }

    private static function parseBusinessHours(mixed $raw): YrnkBusinessHours
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new InvalidYrnkException('business_hours must be a list of [HH:MM, HH:MM] pairs');
        }

        return new YrnkBusinessHours(array_map(
            static function (mixed $pair): YrnkTimeWindow {
                if (! is_array($pair) || ! array_is_list($pair) || count($pair) !== 2
                    || ! is_string($pair[0]) || ! is_string($pair[1])) {
                    throw new InvalidYrnkException('Elements of business_hours must be [HH:MM, HH:MM] pairs');
                }

                return YrnkTimeWindow::fromStrings($pair[0], $pair[1]);
            },
            $raw,
        ));
    }

    /**
     * @return array<string, YrnkCustomDefinition>
     */
    private static function parseCustom(mixed $raw, DateTimeZone $timezone): array
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
            $custom[$name] = self::parseDateSet($value, "custom.{$name}", YrnkCustomDefinition::class, $timezone);
        }

        return $custom;
    }
}
