<?php

namespace Yarunoka\Calendar;

use Yarunoka\YrnkDate;
use Yarunoka\Time\YrnkTimeWindow;
use Yarunoka\Vocabulary\YrnkDayName;
use DateTimeZone;

/**
 * The mirror image of CalendarParser. YrnkCalendar node →
 * RawCalendar. A resolver name reference comes out as the name itself
 * (output that preserves the intent, on the premise that the reader holds
 * the same resolver), so the output is what the input was in every case.
 */
final class YrnkCalendarBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(YrnkCalendar $calendar, DateTimeZone $timezone): array
    {
        $raw = [];

        foreach ([
            'holidays' => $calendar->holidays,
            'business_holidays' => $calendar->businessHolidays,
            'business_days' => $calendar->businessDays,
        ] as $key => $definition) {
            if ($definition !== null) {
                $raw[$key] = self::buildDateSet($definition);
            }
        }

        if ($calendar->workweek !== null) {
            $raw['workweek'] = array_map(
                static fn(YrnkDayName $day): string => $day->value,
                $calendar->workweek->days,
            );
        }

        if ($calendar->businessHours !== null) {
            $raw['business_hours'] = array_map(
                static fn(YrnkTimeWindow $window): array => $window->toStrings(),
                $calendar->businessHours->windows,
            );
        }

        if ($calendar->custom !== []) {
            foreach ($calendar->custom as $name => $definition) {
                $raw['custom'][$name] = self::buildDateSet($definition);
            }
        }

        return $raw;
    }

    /**
     * @return list<string>|string
     */
    private static function buildDateSet(
        YrnkHolidays|YrnkBusinessHolidays|YrnkBusinessDays|YrnkCustomDefinition $definition,
    ): array|string {
        if ($definition->resolver !== null) {
            return $definition->resolver;
        }

        return array_map(
            static fn(YrnkDate $date): string => $date->format('Y-m-d'),
            $definition->dates ?? [],
        );
    }
}
