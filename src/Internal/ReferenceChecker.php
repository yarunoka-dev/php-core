<?php

namespace Yarunoka\Internal;

use Yarunoka\Definitions\Definitions;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Expression\BusinessHourRef;
use Yarunoka\Expression\CustomRef;
use Yarunoka\Expression\DayAtom;
use Yarunoka\Expression\EveryGrid;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\Vocabulary\CalendarWord;
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
    public static function ensureResolvable(iterable $schedules, Definitions $definitions, array $resolvers): void
    {
        foreach ($schedules as $schedule) {
            foreach (self::atomsOf($schedule) as $atom) {
                if ($atom instanceof CustomRef && ! isset($definitions->custom[$atom->name])) {
                    throw new UndefinedNameException("Undefined name: {$atom->name}");
                }

                if ($atom instanceof CalendarWord) {
                    self::ensureCalendarWordDefined($atom, $definitions);
                }
            }

            if ($schedule->times instanceof EveryGrid
                && $schedule->times->between instanceof BusinessHourRef
                && $definitions->businessHours === null) {
                throw new MissingCalendarDataException(
                    'Using business_hour requires the business_hours definition',
                );
            }
        }

        foreach (self::resolverReferences($definitions) as $context => $name) {
            if (! isset($resolvers[$name])) {
                throw new UndefinedNameException("Unregistered resolver name ({$context}): {$name}");
            }
        }
    }

    private static function ensureCalendarWordDefined(CalendarWord $word, Definitions $definitions): void
    {
        $required = match ($word) {
            CalendarWord::Weekday, CalendarWord::Weekend => [],
            CalendarWord::Holiday => ['holidays' => $definitions->holidays],
            CalendarWord::BusinessDay, CalendarWord::BusinessHoliday => [
                'holidays' => $definitions->holidays,
                'business_holidays' => $definitions->businessHolidays,
                'business_days' => $definitions->businessDays,
            ],
        };

        $missing = array_keys(array_filter($required, static fn (?object $definition): bool => $definition === null));

        if ($missing !== []) {
            throw new MissingCalendarDataException(sprintf(
                'Using %s requires the %s definition (write an empty list if there are no such days)',
                $word->value,
                implode(', ', $missing),
            ));
        }
    }

    /**
     * @return iterable<DayAtom>
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
    private static function resolverReferences(Definitions $definitions): iterable
    {
        foreach ([
            'holidays' => $definitions->holidays,
            'business_holidays' => $definitions->businessHolidays,
            'business_days' => $definitions->businessDays,
        ] as $key => $definition) {
            if ($definition?->resolver !== null) {
                yield $key => $definition->resolver;
            }
        }

        foreach ($definitions->custom as $name => $definition) {
            if ($definition->resolver !== null) {
                yield "custom.{$name}" => $definition->resolver;
            }
        }
    }
}
