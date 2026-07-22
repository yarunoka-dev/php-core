<?php

namespace Yarunoka\Builder;

use Yarunoka\Internal\Builder\CalendarBuilder;
use Yarunoka\Yrnk;
use Yarunoka\YrnkSchedule;

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
        private readonly ScheduleBuilder $scheduleBuilder = new ScheduleBuilder(),
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

        $calendar = CalendarBuilder::build($document->calendar);

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
