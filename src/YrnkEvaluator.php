<?php

namespace Yarunoka;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Internal\Evaluation\AtomDayEnumerator;
use Yarunoka\Internal\Evaluation\DayMatcher;
use Yarunoka\Internal\Evaluation\MatchFinder;
use Yarunoka\Internal\Evaluation\ResolvedCalendar;
use Yarunoka\Internal\Evaluation\TimesExpander;
use Yarunoka\Internal\ReferenceChecker;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * The evaluator. A service holding configuration (the definitions and the
 * timezone), asked questions "about a schedule" by handing it a
 * YrnkSchedule. There are three questions — the judgment at a point
 * (matches), the judgment over a period (hasMatchIn), and the enumeration
 * (occurrencesIn). It reads the tree and interprets it with the internal
 * calculators and matchers the content calls for (the layer model,
 * calendar arithmetic, hierarchical evaluation, grid expansion).
 * Questions about the top-level OR (the schedules list) are composed by
 * the caller asking per branch (any for the judgments; a merge of the
 * per-branch lists for the enumeration). The validation every question
 * runs first is also askable on its own (ensureResolvable), for a caller
 * that wants wiring mistakes surfaced eagerly.
 *
 * "Should this fire" does not live here. Firing, catch-up, and grace are
 * expressed by the caller through how it cuts the period it asks about
 * (hasMatchIn(last_run_at, now) is the firing decision). Definitions
 * resolve per question and are not carried between them, so an answer
 * always rests on what the resolvers say at the time it is asked.
 */
final class YrnkEvaluator
{
    public function __construct(
        private readonly YrnkCalendar $calendar,
        private readonly DateTimeZone $timezone,
    ) {}

    /**
     * One built from a whole document, for a caller that has one. The two
     * things this needs are the document's, and taking them apart to hand
     * them back in is where a calendar can end up read on a timezone that
     * is not the one it was written against.
     *
     * The constructor stays for the other case: a runtime that keeps its
     * schedules of its own (rows of a table, say) and reads the rest from
     * its configuration never assembles a document to begin with.
     */
    public static function fromYrnk(Yrnk $document): self
    {
        return new self($document->calendar, $document->timezone);
    }

    /**
     * The machinery for one question. Definitions resolve into it as the
     * question reaches them and go away with it, so an answer never rests
     * on what an earlier question happened to resolve.
     */
    private function finder(): MatchFinder
    {
        $resolved = new ResolvedCalendar($this->calendar, $this->timezone);
        $dayMatcher = new DayMatcher($resolved);

        return new MatchFinder(
            $dayMatcher,
            new AtomDayEnumerator($dayMatcher, $this->timezone),
            new TimesExpander($resolved),
            $this->timezone,
        );
    }

    /**
     * Is the given instant an occurrence? For a timed occurrence the
     * answer is instant equality — the given instant, ignoring anything
     * finer than a second (no scheduled point is finer), equals the
     * occurrence's instant. The comparison is between instants, never
     * wall-clock values. An all-day occurrence matches on the day alone:
     * the answer is yes for every instant whose local date — read in the
     * configured timezone — is that day. Granularity adjustments
     * (rounding to minutes and the like) are done by the caller on the
     * value it passes.
     */
    public function matches(YrnkSchedule $schedule, DateTimeInterface $at): bool
    {
        $this->ensureResolvable([$schedule]);

        return $this->finder()->matches($schedule, DateTimeImmutable::createFromInterface($at));
    }

    /**
     * Is there a scheduled point after $after, through $through? The
     * substance of a firing decision — "is there a scheduled point after
     * the previous run, through now?" maps onto it directly. A point
     * exactly at $after does not count — it was the previous judgment's
     * "now", already counted; a point exactly at $through counts in this
     * judgment, not the next. Each judgment's "now" becomes the next
     * one's start, so every timed point is seen exactly once. An all-day
     * occurrence counts when its day overlaps the period, however late in
     * the day it is asked: a day is due for as long as it lasts. That
     * does not make it a timed occurrence at 00:00.
     */
    public function hasMatchIn(YrnkSchedule $schedule, DateTimeInterface $after, DateTimeInterface $through): bool
    {
        $this->ensureResolvable([$schedule]);

        return $this->finder()->hasMatchIn(
            $schedule,
            DateTimeImmutable::createFromInterface($after),
            DateTimeImmutable::createFromInterface($through),
        );
    }

    /**
     * Which occurrences lie from $from through $through? The answer is
     * the occurrence set cut to [$from, $through] — every timed
     * occurrence whose instant lies in the closed interval, and every
     * all-day occurrence whose day overlaps it. Timed occurrences are
     * answered as instants on the configured timezone's clock, all-day
     * occurrences as dates (YrnkDate); the two kinds stay distinct, and
     * the answer is in ascending order, an all-day occurrence taking the
     * start of its day as its place in the order. Unlike the judgment
     * over a period — whose start is excluded because that instant was
     * the previous judgment's "now" — an enumeration has no previous
     * window: the caller names two instants, and both are part of what it
     * names. Adjacent windows sharing a boundary instant therefore both
     * contain a point exactly on it, and a caller that means to exclude a
     * boundary moves it.
     *
     * @return list<YrnkDate|YrnkDateTime>
     */
    public function occurrencesIn(YrnkSchedule $schedule, DateTimeInterface $from, DateTimeInterface $through): array
    {
        $this->ensureResolvable([$schedule]);

        return $this->finder()->occurrencesIn(
            $schedule,
            DateTimeImmutable::createFromInterface($from),
            DateTimeImmutable::createFromInterface($through),
        );
    }

    /**
     * Would every name these schedules write be answered here? The same
     * validation every question runs first — a hand-composed tree may
     * arrive, so resolvability of references is checked before evaluation
     * (a document via YrnkParser is guarded twice) — reachable on its
     * own, for a caller that wants a wiring mistake surfaced before a
     * schedule is stored or a question is asked. Consults the definitions
     * and the bindings' names only and never invokes a resolver, so
     * passing says the references are answerable, not what the answers
     * will be.
     *
     * @param  iterable<YrnkSchedule>  $schedules
     */
    public function ensureResolvable(iterable $schedules): void
    {
        ReferenceChecker::ensureResolvable($schedules, $this->calendar);
    }
}
