<?php

namespace Yarunoka\Tests\Support;

use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkSchedule;
use DateInterval;
use DateTimeImmutable;

/**
 * A caller-side simulator implementing the typical firing-decision
 * pattern from the docs as-is.
 *
 * It holds last_run_at and, on every tick, asks the evaluator "is there a
 * matching date-time in (last_run_at, now]?"; if so it fires and updates
 * last_run_at. Grace only trims the lower bound of the question interval.
 */
final class RoutinePoller
{
    private DateTimeImmutable $lastRunAt;

    /** @var list<YrnkSchedule> */
    private readonly array $schedules;

    /**
     * @param  YrnkSchedule|list<YrnkSchedule>  $schedule  A list is the top-level OR.
     *                                                     Composing the group (fire when any has a point) is the caller's job
     */
    public function __construct(
        private readonly YrnkEvaluator $evaluator,
        YrnkSchedule|array $schedule,
        DateTimeImmutable $startedAt,
        private readonly ?DateInterval $grace = null,
    ) {
        $this->schedules = $schedule instanceof YrnkSchedule ? [$schedule] : $schedule;
        $this->lastRunAt = $startedAt;
    }

    /**
     * One polling round. True when it fired.
     */
    public function tick(DateTimeImmutable $now): bool
    {
        $from = $this->lastRunAt;

        if ($this->grace !== null) {
            $from = max($from, $now->sub($this->grace));
        }

        foreach ($this->schedules as $schedule) {
            if ($this->evaluator->hasMatchIn($schedule, $from, $now)) {
                $this->lastRunAt = $now;

                return true;
            }
        }

        return false;
    }
}
