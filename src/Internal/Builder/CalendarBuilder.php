<?php

namespace Yarunoka\Internal\Builder;

use Yarunoka\Calendar\BusinessDays;
use Yarunoka\Calendar\BusinessHolidays;
use Yarunoka\Calendar\Calendar;
use Yarunoka\Calendar\CustomDefinition;
use Yarunoka\Calendar\Holidays;
use Yarunoka\Exceptions\InvalidCalendarDataException;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\YrnkDate;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\DayName;
use DateTimeZone;

/**
 * The mirror image of CalendarParser. Calendar node →
 * RawCalendar. A resolver name reference comes out as the name itself
 * (output that preserves the intent, on the premise that the reader holds
 * the same resolver). A Closure (deferred) is not writable in the DSL, so
 * it is resolved and folded into a snapshot (a date list).
 *
 * @internal
 */
final class CalendarBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Calendar $calendar, DateTimeZone $timezone): array
    {
        $raw = [];

        foreach ([
            'holidays' => $calendar->holidays,
            'business_holidays' => $calendar->businessHolidays,
            'business_days' => $calendar->businessDays,
        ] as $key => $definition) {
            if ($definition !== null) {
                $raw[$key] = self::buildDateSet($definition, $key, $timezone);
            }
        }

        if ($calendar->workweek !== null) {
            $raw['workweek'] = array_map(
                static fn(DayName $day): string => $day->value,
                $calendar->workweek->days,
            );
        }

        if ($calendar->businessHours !== null) {
            $raw['business_hours'] = array_map(
                static fn(TimeWindow $window): array => $window->toStrings(),
                $calendar->businessHours->windows,
            );
        }

        if ($calendar->custom !== []) {
            foreach ($calendar->custom as $name => $definition) {
                $raw['custom'][$name] = self::buildDateSet($definition, "custom.{$name}", $timezone);
            }
        }

        return $raw;
    }

    /**
     * @return list<string>|string
     */
    private static function buildDateSet(
        Holidays|BusinessHolidays|BusinessDays|CustomDefinition $definition,
        string $context,
        DateTimeZone $timezone,
    ): array|string {
        if ($definition->resolver !== null) {
            return $definition->resolver;
        }

        if ($definition->dates !== null) {
            return array_map(
                static fn(YrnkDate $date): string => $date->format('Y-m-d'),
                $definition->dates,
            );
        }

        // The deferred snapshot. The return value is user data, so it is
        // validated.
        $resolved = $definition->closure !== null ? ($definition->closure)() : null;

        if (! is_array($resolved)) {
            throw new InvalidCalendarDataException("{$context}: the closure must return a list of date strings");
        }

        return array_map(
            static function (mixed $date) use ($context, $timezone): string {
                if (! is_string($date)) {
                    throw new InvalidCalendarDataException("{$context}: dates must be YYYY-MM-DD strings");
                }

                try {
                    return (new YrnkDate($date, $timezone))->format('Y-m-d');
                } catch (InvalidValueException $e) {
                    throw new InvalidCalendarDataException("{$context}: {$e->getMessage()}");
                }
            },
            array_values($resolved),
        );
    }
}
