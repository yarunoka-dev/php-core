<?php

namespace Yarunoka\Internal;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Exceptions\UnregisteredResolverException;
use Yarunoka\Schedule\BusinessHourRef;
use Yarunoka\Schedule\CustomRef;
use Yarunoka\Schedule\DayAtomInterface;
use Yarunoka\Schedule\EveryGrid;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\Internal\Vocabulary\CalendarWord;
use Yarunoka\YrnkSchedule;

/**
 * Checks schedules against the definitions and validates that every
 * reference resolves. Shared by the holders of the definitions
 * (YrnkParser at parse time, YrnkEvaluator before evaluation). Never a
 * silent "no match".
 *
 * @internal
 */
final class ReferenceChecker
{
    /**
     * @param  iterable<YrnkSchedule>  $schedules
     * @param  array<string, (\Closure(): list<string>)|YrnkResolverInterface>  $resolvers
     */
    public static function ensureResolvable(iterable $schedules, YrnkCalendar $calendar, array $resolvers): void
    {
        foreach ($schedules as $schedule) {
            foreach (self::atomsOf($schedule) as $atom) {
                if ($atom instanceof CustomRef && ! isset($calendar->custom[$atom->name])) {
                    throw new UndefinedNameException("Undefined name: {$atom->name}");
                }

                if ($atom instanceof CalendarWord) {
                    self::ensureCalendarWordDefined($atom, $calendar);
                }
            }

            if ($schedule->times instanceof EveryGrid
                && $schedule->times->between instanceof BusinessHourRef
                && $calendar->businessHours === null) {
                throw new MissingCalendarDataException(
                    'Using business_hour requires the business_hours definition',
                );
            }
        }

        foreach (self::resolverReferences($calendar) as $context => $name) {
            if (! isset($resolvers[$name])) {
                throw new UnregisteredResolverException("Unregistered resolver name ({$context}): {$name}");
            }
        }
    }

    private static function ensureCalendarWordDefined(CalendarWord $word, YrnkCalendar $calendar): void
    {
        $required = match ($word) {
            CalendarWord::Weekday, CalendarWord::Weekend => [],
            CalendarWord::Holiday => ['holidays' => $calendar->holidays],
            CalendarWord::BusinessDay, CalendarWord::BusinessHoliday => [
                'holidays' => $calendar->holidays,
                'business_holidays' => $calendar->businessHolidays,
                'business_days' => $calendar->businessDays,
            ],
        };

        $missing = array_keys(array_filter($required, static fn(?object $definition): bool => $definition === null));

        if ($missing !== []) {
            throw new MissingCalendarDataException(sprintf(
                'Using %s requires the %s definition (write an empty list if there are no such days)',
                $word->value,
                implode(', ', $missing),
            ));
        }
    }

    /**
     * @return iterable<DayAtomInterface>
     */
    private static function atomsOf(YrnkSchedule $schedule): iterable
    {
        yield from $schedule->days->atoms ?? [];

        if ($schedule->shift !== null) {
            yield $schedule->shift->condition;
        }

        if ($schedule->if !== null) {
            yield $schedule->if->condition;
        }
    }

    /**
     * @return iterable<string, string> context label → resolver name
     */
    private static function resolverReferences(YrnkCalendar $calendar): iterable
    {
        foreach ([
            'holidays' => $calendar->holidays,
            'business_holidays' => $calendar->businessHolidays,
            'business_days' => $calendar->businessDays,
        ] as $key => $definition) {
            if ($definition?->resolver !== null) {
                yield $key => $definition->resolver;
            }
        }

        foreach ($calendar->custom as $name => $definition) {
            if ($definition->resolver !== null) {
                yield "custom.{$name}" => $definition->resolver;
            }
        }
    }
}
