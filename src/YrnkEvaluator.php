<?php

namespace Yarunoka;

use Yarunoka\Calendar\Calendar;
use Yarunoka\Internal\Evaluation\AtomDayEnumerator;
use Yarunoka\Internal\Evaluation\DayMatcher;
use Yarunoka\Internal\Evaluation\MatchFinder;
use Yarunoka\Internal\Evaluation\ResolvedCalendar;
use Yarunoka\Internal\Evaluation\TimesExpander;
use Yarunoka\Internal\ReferenceChecker;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * The evaluator. A service holding configuration (the definitions and the
 * timezone), asked questions "about a schedule" by handing it a
 * YrnkSchedule. There are two questions — the single check (matches) and
 * the interval check (hasMatchIn). It reads the tree and interprets it
 * with the internal calculators and matchers the content calls for (the
 * layer model, calendar arithmetic, hierarchical evaluation, grid
 * expansion). Questions about the top-level OR (the schedules list) are
 * composed by the caller asking per branch (any).
 *
 * "Should this fire" does not live here. Firing, catch-up, and grace are
 * expressed by the caller through how it cuts the question interval
 * (hasMatchIn(last_run_at, now) is the firing decision). Resolver results
 * are memoized for the lifetime of this instance (at most once).
 */
final class YrnkEvaluator
{
    private readonly MatchFinder $finder;

    /**
     * @param  array<string, (Closure(): list<string>)|YrnkResolverInterface>  $resolvers  Resolver name → date list supplier (a function | the resolver contract)
     */
    public function __construct(
        private readonly Calendar $calendar,
        private readonly DateTimeZone $timezone,
        private readonly array $resolvers = [],
    ) {
        $resolved = new ResolvedCalendar($calendar, $resolvers);
        $dayMatcher = new DayMatcher($resolved);
        $this->finder = new MatchFinder(
            $dayMatcher,
            new AtomDayEnumerator($dayMatcher),
            new TimesExpander($resolved),
            $timezone,
        );
    }

    /**
     * Does this date-time match? Beyond the day decision, the schedule
     * itself decides whether the time takes part — with times, the value
     * must equal one of the points expanded on the configured timezone's
     * wall clock (to the second); with allday, only the day is checked.
     * Granularity adjustments (rounding to minutes and the like) are done
     * by the caller on the value it passes.
     */
    public function matches(YrnkSchedule $schedule, DateTimeInterface $at): bool
    {
        $this->ensureResolvable($schedule);

        return $this->finder->matches($schedule, DateTimeImmutable::createFromInterface($at));
    }

    /**
     * Is there a matching date-time in the half-open interval (from, to]?
     * The substance of a firing decision — "is there a scheduled point in
     * (last_run_at, now]?" maps onto it directly. A point at from does
     * not count (preventing a recount of the previous run); a point at to
     * counts.
     */
    public function hasMatchIn(YrnkSchedule $schedule, DateTimeInterface $from, DateTimeInterface $to): bool
    {
        $this->ensureResolvable($schedule);

        return $this->finder->hasMatchIn(
            $schedule,
            DateTimeImmutable::createFromInterface($from),
            DateTimeImmutable::createFromInterface($to),
        );
    }

    /**
     * A hand-composed tree may arrive, so resolvability of references is
     * validated before evaluation (a document via YrnkParser is guarded
     * twice).
     */
    private function ensureResolvable(YrnkSchedule $schedule): void
    {
        ReferenceChecker::ensureResolvable([$schedule], $this->calendar, $this->resolvers);
    }
}
