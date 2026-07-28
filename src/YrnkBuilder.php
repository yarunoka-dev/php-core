<?php

namespace Yarunoka;

use Yarunoka\Calendar\YrnkCalendarBuilder;
use Yarunoka\Schedule\YrnkScheduleBuilder;

/**
 * The mirror image of YrnkParser. Yrnk → a Yrnk document (an array /
 * JSON). Round-tripping is the identity: building a Yrnk parsed from the
 * DSL yields the original array representation (the one exception is a
 * hand-composed Yrnk containing Closures, which are folded into
 * snapshots).
 */
final class YrnkBuilder
{
    public function __construct(
        private readonly YrnkScheduleBuilder $scheduleBuilder = new YrnkScheduleBuilder(),
        private readonly YrnkCalendarBuilder $calendarBuilder = new YrnkCalendarBuilder(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Yrnk $document): array
    {
        $raw = [
            'version' => $document->version,
            'timezone' => $document->timezone->getName(),
        ];

        $calendar = $this->calendarBuilder->build($document->calendar, $document->timezone);

        if ($calendar !== []) {
            $raw['calendar'] = $calendar;
        }

        $raw['schedules'] = array_map(
            fn(YrnkSchedule $schedule): array => $this->scheduleBuilder->build($schedule),
            $document->schedules,
        );

        return $raw;
    }

    public function toJson(Yrnk $document): string
    {
        return json_encode($this->build($document), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
