<?php

namespace Yarunoka\Builder;

use Yarunoka\Expression\AllDay;
use Yarunoka\Expression\EverySequence;
use Yarunoka\Internal\Builder\DayExpressionBuilder;
use Yarunoka\Internal\Builder\IfGuardBuilder;
use Yarunoka\Internal\Builder\ShiftBuilder;
use Yarunoka\Internal\Builder\TimesBuilder;
use Yarunoka\YrnkSchedule;

/**
 * The mirror image of ScheduleParser. YrnkSchedule → RawSchedule (one
 * element of the DSL's schedules[]).
 */
final class ScheduleBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(YrnkSchedule $schedule): array
    {
        $raw = [];

        if ($schedule->from !== null) {
            $raw['from'] = $schedule->from->toString();
        }

        if ($schedule->until !== null) {
            $raw['until'] = $schedule->until->toString();
        }

        if ($schedule->years !== null) {
            $raw['years'] = $schedule->years;
        }

        if ($schedule->months !== null) {
            $raw['months'] = $schedule->months;
        }

        if ($schedule->days !== null) {
            $raw['days'] = DayExpressionBuilder::build($schedule->days);
        }

        if ($schedule->shift !== null) {
            $raw['shift'] = ShiftBuilder::build($schedule->shift);
        }

        if ($schedule->if !== null) {
            $raw['if'] = IfGuardBuilder::build($schedule->if);
        }

        if ($schedule->times instanceof AllDay) {
            $raw['allday'] = true;
        } elseif ($schedule->times instanceof EverySequence) {
            $raw['every'] = [$schedule->times->amount, $schedule->times->unit->value];
        } else {
            $raw['times'] = TimesBuilder::build($schedule->times);
        }

        return $raw;
    }
}
