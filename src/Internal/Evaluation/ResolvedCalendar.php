<?php

namespace Yarunoka\Internal\Evaluation;

use Yarunoka\Calendar\YrnkBusinessDays;
use Yarunoka\Calendar\YrnkBusinessHolidays;
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkCustomDefinition;
use Yarunoka\Calendar\YrnkHolidays;
use Yarunoka\Exceptions\InvalidCalendarDataException;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Exceptions\UnregisteredResolverException;
use Yarunoka\Resolvers\YrnkResolverContainer;
use Yarunoka\Time\YrnkTimeWindow;
use Yarunoka\Vocabulary\YrnkDayName;
use Yarunoka\YrnkDate;
use DateTimeZone;

/**
 * Resolution of the definitions for one question. A resolver is asked
 * for the year a consulted day falls in, and the answer is held
 * until the question is done — the working data of a single computation,
 * not a cache: a new question resolves anew, so a caller that wants
 * results kept holds them in its own resolver.
 *
 * @internal
 */
final class ResolvedCalendar
{
    /** @var array<string, array<int|string, array<string, true>>> Resolved date sets (definition => year|'all' => 'Y-m-d' => true) */
    private array $sets = [];

    /** @var array<string, true>|null The workweek day set (YrnkDayName->value => true) */
    private ?array $workweekSet = null;

    public function __construct(
        private readonly YrnkCalendar $calendar,
        private readonly DateTimeZone $timezone,
        private readonly YrnkResolverContainer $resolvers = new YrnkResolverContainer(),
    ) {}

    public function holidayContains(YrnkDate $date): bool
    {
        return isset($this->dateSet('holidays', $this->calendar->holidays, $date)[$date->format('Y-m-d')]);
    }

    public function businessHolidayContains(YrnkDate $date): bool
    {
        return isset($this->dateSet('business_holidays', $this->calendar->businessHolidays, $date)[$date->format('Y-m-d')]);
    }

    public function businessDayContains(YrnkDate $date): bool
    {
        return isset($this->dateSet('business_days', $this->calendar->businessDays, $date)[$date->format('Y-m-d')]);
    }

    public function customContains(string $name, YrnkDate $date): bool
    {
        $definition = $this->calendar->custom[$name]
            ?? throw new UndefinedNameException("Undefined name: {$name}");

        return isset($this->dateSet("custom.{$name}", $definition, $date)[$date->format('Y-m-d')]);
    }

    public function isInWorkweek(YrnkDayName $dayOfWeek): bool
    {
        if ($this->workweekSet === null) {
            $days = $this->calendar->workweek->days
                ?? [YrnkDayName::Mon, YrnkDayName::Tue, YrnkDayName::Wed, YrnkDayName::Thu, YrnkDayName::Fri];
            $this->workweekSet = [];

            foreach ($days as $day) {
                $this->workweekSet[$day->value] = true;
            }
        }

        return isset($this->workweekSet[$dayOfWeek->value]);
    }

    /**
     * @return list<YrnkTimeWindow>
     */
    public function businessHourWindows(): array
    {
        $businessHours = $this->calendar->businessHours
            ?? throw new MissingCalendarDataException('Using business_hour requires the business_hours definition');

        return $businessHours->windows;
    }

    /**
     * The set to consult for this day. A written date list stands whole,
     * so it is built once; a resolved list is asked for the year the day
     * falls in, since that is the granularity the question reaches.
     *
     * @return array<string, true>
     */
    private function dateSet(
        string $key,
        YrnkHolidays|YrnkBusinessHolidays|YrnkBusinessDays|YrnkCustomDefinition|null $definition,
        YrnkDate $date,
    ): array {
        if ($definition === null) {
            // A safeguard: the reference validation of YrnkParser /
            // YrnkEvaluator should have rejected this already.
            throw new MissingCalendarDataException("The {$key} definition is required");
        }

        $scope = $definition->dates !== null ? 'all' : (int) $date->format('Y');

        return $this->sets[$key][$scope] ??= $this->resolve($key, $definition, $scope);
    }

    /**
     * @return array<string, true>
     */
    private function resolve(
        string $key,
        YrnkHolidays|YrnkBusinessHolidays|YrnkBusinessDays|YrnkCustomDefinition $definition,
        int|string $scope,
    ): array {
        if ($definition->dates !== null) {
            $set = [];

            foreach ($definition->dates as $date) {
                $set[$date->format('Y-m-d')] = true;
            }

            return $set;
        }

        if ($definition->resolver === null) {
            throw new MissingCalendarDataException("The {$key} definition has no source of dates");
        }

        $resolver = $this->resolvers->get($definition->resolver)
            ?? throw new UnregisteredResolverException("Unregistered resolver name ({$key}): {$definition->resolver}");

        $from = new YrnkDate("{$scope}-01-01", $this->timezone);
        $through = new YrnkDate("{$scope}-12-31", $this->timezone);

        return $this->dateSetOf($resolver->resolve($from, $through), $key);
    }

    /**
     * The set a resolver handed back. Takes mixed on purpose: the value
     * crossed the boundary from host code, so what the contract says it is
     * has to be checked rather than assumed — the spec asks implementations
     * to validate that a resolver yields date literals.
     *
     * @return array<string, true>
     */
    private function dateSetOf(mixed $resolved, string $key): array
    {
        if (! is_array($resolved)) {
            throw new InvalidCalendarDataException("{$key}: the resolver must return a list of date strings");
        }

        $set = [];

        foreach ($resolved as $date) {
            if (! is_string($date)) {
                throw new InvalidCalendarDataException("{$key}: dates must be YYYY-MM-DD strings");
            }

            try {
                $set[(new YrnkDate($date, $this->timezone))->format('Y-m-d')] = true;
            } catch (InvalidValueException $e) {
                throw new InvalidCalendarDataException("{$key}: {$e->getMessage()}");
            }
        }

        return $set;
    }
}
