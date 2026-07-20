<?php

namespace Yarunoka\Definitions;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Time\TimeWindow;

/**
 * The window list behind the business_hour vocabulary (a built-in
 * definition). Kept in written order. Overlapping windows would be the
 * quiet accident of duplicated grid points, so the invariant rejects them
 * (the intervals are half-open, so touching windows do not overlap and
 * are legal).
 */
final readonly class BusinessHours
{
    /** @var non-empty-list<TimeWindow> */
    public array $windows;

    /**
     * @param  list<TimeWindow>  $windows  Unvalidated input. Empty lists or overlapping windows violate the invariants
     */
    public function __construct(array $windows)
    {
        if ($windows === []) {
            throw new InvalidValueException('business_hours cannot be empty');
        }

        $sorted = $windows;
        usort($sorted, static fn(TimeWindow $a, TimeWindow $b): int => $a->startSeconds <=> $b->startSeconds);

        foreach ($sorted as $i => $window) {
            if ($i > 0 && $window->startSeconds < $sorted[$i - 1]->endSeconds) {
                throw new InvalidValueException('Overlapping windows in business_hours');
            }
        }

        $this->windows = $windows;
    }
}
