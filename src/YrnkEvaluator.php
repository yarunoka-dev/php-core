<?php

namespace Yarunoka;

use Yarunoka\Calendar\YrnkCalendar;
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
 * YrnkSchedule. There are three questions — the single check (matches),
 * the interval check (hasMatchIn), and the enumeration (occurrencesIn).
 * It reads the tree and interprets it with the internal calculators and
 * matchers the content calls for (the layer model, calendar arithmetic,
 * hierarchical evaluation, grid expansion). Questions about the top-level
 * OR (the schedules list) are composed by the caller asking per branch
 * (any for the checks; a merge of the per-branch lists for the
 * enumeration).
 *
 * "Should this fire" does not live here. Firing, catch-up, and grace are
 * expressed by the caller through how it cuts the question interval
 * (hasMatchIn(last_run_at, now) is the firing decision). Definitions
 * resolve per question and are not carried between them, so an answer
 * always rests on what the resolvers say at the time it is asked.
 */
final class YrnkEvaluator
{
    /**
     * @param  array<string, (Closure(YrnkDate, YrnkDate): list<string>)|YrnkResolverInterface>  $resolvers  Resolver name → date list supplier (a function | the resolver contract)
     */
    public function __construct(
        private readonly YrnkCalendar $calendar,
        private readonly DateTimeZone $timezone,
        private readonly array $resolvers = [],
    ) {}

    /**
     * The machinery for one question. Definitions resolve into it as the
     * question reaches them and go away with it, so an answer never rests
     * on what an earlier question happened to resolve.
     */
    private function finder(): MatchFinder
    {
        $resolved = new ResolvedCalendar($this->calendar, $this->resolvers, $this->timezone);
        $dayMatcher = new DayMatcher($resolved);

        return new MatchFinder(
            $dayMatcher,
            new AtomDayEnumerator($dayMatcher, $this->timezone),
            new TimesExpander($resolved),
            $this->timezone,
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

        return $this->finder()->matches($schedule, DateTimeImmutable::createFromInterface($at));
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

        return $this->finder()->hasMatchIn(
            $schedule,
            DateTimeImmutable::createFromInterface($from),
            DateTimeImmutable::createFromInterface($to),
        );
    }

    /**
     * The occurrences in the closed interval [from, to], in ascending
     * order of comparison instant. Timed occurrences are answered as
     * instants on the configured timezone's clock, all-day occurrences
     * as dates (YrnkDate) — the two kinds never merge. Unlike
     * hasMatchIn, whose start is excluded as the previous judgment's
     * "now", both boundary instants are part of what the caller names:
     * adjacent windows sharing a boundary instant both contain a point
     * exactly on it, and a caller that means to exclude a boundary moves
     * it.
     *
     * @return list<YrnkDate|YrnkDateTime>
     */
    public function occurrencesIn(YrnkSchedule $schedule, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $this->ensureResolvable($schedule);

        return $this->finder()->occurrencesIn(
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
