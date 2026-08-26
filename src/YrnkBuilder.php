<?php

namespace Yarunoka;

use Yarunoka\Calendar\YrnkCalendarBuilder;
use Yarunoka\Schedule\YrnkScheduleBuilder;
use stdClass;

/**
 * The mirror image of YrnkParser. Yrnk → a Yrnk document (an array /
 * JSON). Round-tripping is the identity: building a Yrnk parsed from the
 * DSL yields the original array representation.
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
        $raw = [];

        // Annotations lead: a labeled document tells the reader what it is
        // before how to read it.
        if ($document->label !== null) {
            $raw['label'] = $document->label;
        }

        if ($document->description !== null) {
            $raw['description'] = $document->description;
        }

        $raw['version'] = $document->version;
        $raw['timezone'] = $document->timezone->getName();

        // A document that leaves nothing to its host omits the key rather
        // than writing an empty list.
        if ($document->resolvers !== []) {
            $raw['resolvers'] = $document->resolvers;
        }

        $calendar = $this->calendarBuilder->build($document->calendar, $document->timezone);

        if ($document->calendar->authoredEmpty) {
            // The 1.0 spelling "calendar": {} comes back as written — an
            // object, which an empty PHP array would not encode as.
            $raw['calendar'] = new stdClass();
        } elseif ($calendar !== []) {
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
