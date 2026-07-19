<?php

namespace Yarunoka\Internal\Evaluation;

use Yarunoka\Definitions\BusinessDays;
use Yarunoka\Definitions\BusinessHolidays;
use Yarunoka\Definitions\CustomDefinition;
use Yarunoka\Definitions\Definitions;
use Yarunoka\Definitions\Holidays;
use Yarunoka\Exceptions\InvalidCalendarDataException;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\Time\LocalDate;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\DayName;
use Closure;

/**
 * Resolution and memoization of the definitions. A resolver / Closure is
 * called at most once in the lifetime of this instance (a lazy val).
 * Freshness rides on the lifetime of the YrnkEvaluator — the DI scope.
 *
 * @internal
 */
final class ResolvedDefinitions
{
    /** @var array<string, array<string, true>> Resolved date sets ('Y-m-d' => true) */
    private array $sets = [];

    /** @var array<string, true>|null The workweek day set (DayName->value => true) */
    private ?array $workweekSet = null;

    /**
     * @param  array<string, (Closure(): list<string>)|YrnkResolverInterface>  $resolvers
     */
    public function __construct(
        private readonly Definitions $definitions,
        private readonly array $resolvers,
    ) {}

    public function holidayContains(LocalDate $date): bool
    {
        return isset($this->dateSet('holidays', $this->definitions->holidays)[$date->toString()]);
    }

    public function businessHolidayContains(LocalDate $date): bool
    {
        return isset($this->dateSet('business_holidays', $this->definitions->businessHolidays)[$date->toString()]);
    }

    public function businessDayContains(LocalDate $date): bool
    {
        return isset($this->dateSet('business_days', $this->definitions->businessDays)[$date->toString()]);
    }

    public function customContains(string $name, LocalDate $date): bool
    {
        $definition = $this->definitions->custom[$name]
            ?? throw new UndefinedNameException("Undefined name: {$name}");

        return isset($this->dateSet("custom.{$name}", $definition)[$date->toString()]);
    }

    public function isInWorkweek(DayName $dayOfWeek): bool
    {
        if ($this->workweekSet === null) {
            $days = $this->definitions->workweek->days
                ?? [DayName::Mon, DayName::Tue, DayName::Wed, DayName::Thu, DayName::Fri];
            $this->workweekSet = [];

            foreach ($days as $day) {
                $this->workweekSet[$day->value] = true;
            }
        }

        return isset($this->workweekSet[$dayOfWeek->value]);
    }

    /**
     * @return list<TimeWindow>
     */
    public function businessHourWindows(): array
    {
        $businessHours = $this->definitions->businessHours
            ?? throw new MissingCalendarDataException('Using business_hour requires the business_hours definition');

        return $businessHours->windows;
    }

    /**
     * @return array<string, true>
     */
    private function dateSet(
        string $key,
        Holidays|BusinessHolidays|BusinessDays|CustomDefinition|null $definition,
    ): array {
        if (isset($this->sets[$key])) {
            return $this->sets[$key];
        }

        if ($definition === null) {
            // A safeguard: the reference validation of YrnkParser /
            // YrnkEvaluator should have rejected this already.
            throw new MissingCalendarDataException("The {$key} definition is required");
        }

        return $this->sets[$key] = $this->resolve($key, $definition);
    }

    /**
     * @return array<string, true>
     */
    private function resolve(
        string $key,
        Holidays|BusinessHolidays|BusinessDays|CustomDefinition $definition,
    ): array {
        if ($definition->dates !== null) {
            $set = [];

            foreach ($definition->dates as $date) {
                $set[$date->toString()] = true;
            }

            return $set;
        }

        $resolve = $definition->resolver !== null
            ? ($this->resolvers[$definition->resolver]
                ?? throw new UndefinedNameException("Unregistered resolver name ({$key}): {$definition->resolver}"))
            : $definition->closure;

        if ($resolve === null) {
            throw new MissingCalendarDataException("The {$key} definition has no source of dates");
        }

        $resolved = $resolve instanceof YrnkResolverInterface ? $resolve->resolve() : $resolve();

        if (! is_array($resolved)) {
            throw new InvalidCalendarDataException("{$key}: the resolver must return a list of date strings");
        }

        $set = [];

        foreach ($resolved as $date) {
            if (! is_string($date)) {
                throw new InvalidCalendarDataException("{$key}: dates must be YYYY-MM-DD strings");
            }

            try {
                $set[LocalDate::fromString($date)->toString()] = true;
            } catch (InvalidValueException $e) {
                throw new InvalidCalendarDataException("{$key}: {$e->getMessage()}");
            }
        }

        return $set;
    }
}
