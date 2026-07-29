<?php

namespace Yarunoka\Internal;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Exceptions\UnregisteredResolverException;
use Yarunoka\Schedule\BusinessHourRef;
use Yarunoka\Schedule\DateSetRef;
use Yarunoka\Schedule\DayAtomInterface;
use Yarunoka\Schedule\EveryGrid;
use Yarunoka\Internal\Vocabulary\CalendarWord;
use Yarunoka\YrnkDate;
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
     */
    public static function ensureResolvable(iterable $schedules, YrnkCalendar $calendar): void
    {
        foreach ($schedules as $schedule) {
            foreach (self::atomsOf($schedule) as $atom) {
                if ($atom instanceof DateSetRef && ! self::resolves($atom->name, $calendar)) {
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

        foreach (self::nameReferences($calendar) as $context => $name) {
            if (! self::resolves($name, $calendar)) {
                throw new UnregisteredResolverException("No resolver is bound to this name ({$context}): {$name}");
            }
        }
    }

    /**
     * A name denotes a date set, resolved either inside the document (an
     * entry of date_sets) or outside it (a binding the host supplies).
     * Which of the two makes no difference to where the name may be
     * written, so both are consulted wherever one is checked.
     */
    private static function resolves(string $name, YrnkCalendar $calendar): bool
    {
        return isset($calendar->dateSets[$name]) || $calendar->resolverContainer->has($name);
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

        $missing = array_keys(array_filter($required, static fn(object|string|null $definition): bool => $definition === null));

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
     * The names written where a date list is expected. An entry of
     * date_sets is not among them: it carries its dates itself.
     *
     * @return iterable<string, string> context label → name
     */
    private static function nameReferences(YrnkCalendar $calendar): iterable
    {
        foreach ([
            'holidays' => $calendar->holidays,
            'business_holidays' => $calendar->businessHolidays,
            'business_days' => $calendar->businessDays,
        ] as $key => $definition) {
            if (is_string($definition)) {
                yield $key => $definition;
            }
        }
    }
}
