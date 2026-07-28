<?php

namespace Yarunoka\Internal\Evaluation;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Schedule\BusinessHourRef;
use Yarunoka\Schedule\EveryGrid;
use Yarunoka\Schedule\FixedTimes;
use Yarunoka\Schedule\TimesSpecInterface;
use Yarunoka\Time\TimeOfDay;
use Yarunoka\Time\YrnkTimeWindow;

/**
 * Expansion of times into the scheduled points within one day (seconds
 * from midnight). The nodes keep the written notation, so sorting and
 * laying out the grid happen here. The grid anchors at the start of the
 * window; windows are the half-open interval [start, end). The two time
 * parts that lay out no point within a day never reach here — allday,
 * which a range answers for by whether its day overlaps it, and the
 * interval every, which counts across days on its own arithmetic; the
 * finder decides both before asking.
 *
 * @internal
 */
final readonly class TimesExpander
{
    public function __construct(private ResolvedCalendar $calendar) {}

    /**
     * @return list<int> In ascending order
     */
    public function secondsOf(TimesSpecInterface $times): array
    {
        if ($times instanceof FixedTimes) {
            $seconds = array_map(
                static fn(TimeOfDay $time): int => $time->secondsFromMidnight,
                $times->times,
            );
            sort($seconds);

            return $seconds;
        }

        if ($times instanceof EveryGrid) {
            $step = $times->amount * $times->unit->seconds();
            $windows = match (true) {
                $times->between instanceof YrnkTimeWindow => [$times->between],
                $times->between instanceof BusinessHourRef => $this->calendar->businessHourWindows(),
                default => [YrnkTimeWindow::fromStrings('00:00', '24:00')],
            };
            $points = [];

            foreach ($windows as $window) {
                for ($t = $window->startSeconds; $t < $window->endSeconds; $t += $step) {
                    $points[] = $t;
                }
            }

            sort($points);

            return $points;
        }

        // allday and the interval every are decided by the finder before
        // it gets here — neither lays out points within a day.
        throw new InvalidValueException(
            'The times expander only lays out points within a day: ' . get_debug_type($times),
        );
    }
}
