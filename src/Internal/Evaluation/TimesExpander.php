<?php

namespace Yarunoka\Internal\Evaluation;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Schedule\AllDay;
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
 * window; windows are the half-open interval [start, end). allday
 * carries no time of its own: a range answers for it by whether its day
 * overlaps that range, so the finder decides it without expanding any
 * point here.
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
        if ($times instanceof AllDay) {
            return [0];
        }

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

        throw new InvalidValueException('Unknown times node: ' . get_debug_type($times));
    }
}
