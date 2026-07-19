<?php

namespace Yarunoka\Internal\Builder;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Expression\BusinessHourRef;
use Yarunoka\Expression\EveryGrid;
use Yarunoka\Expression\FixedTimes;
use Yarunoka\Expression\TimesSpec;
use Yarunoka\Time\TimeOfDay;

/**
 * The mirror image of TimesParser. Times node → RawTimes (AllDay never
 * arrives here — ScheduleBuilder renders it as "allday": true).
 *
 * @internal
 */
final class TimesBuilder
{
    /**
     * @return list<string>|array{every: array{int, string}, between?: string|array{string, string}}
     */
    public static function build(TimesSpec $times): array
    {
        if ($times instanceof FixedTimes) {
            return array_map(
                static fn (TimeOfDay $time): string => $time->toString(),
                $times->times,
            );
        }

        if ($times instanceof EveryGrid) {
            $raw = ['every' => [$times->amount, $times->unit->value]];

            if ($times->between instanceof BusinessHourRef) {
                $raw['between'] = 'business_hour';
            } elseif ($times->between !== null) {
                $raw['between'] = $times->between->toStrings();
            }

            return $raw;
        }

        throw new InvalidValueException('Unknown times node: '.get_debug_type($times));
    }
}
