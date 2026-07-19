<?php

namespace Yarunoka\Time;

use Yarunoka\Exceptions\InvalidValueException;

/**
 * A time of day expressed as seconds elapsed since midnight. Time literals
 * in the DSL are zero-padded HH:MM only ("24:00" is a token allowed only
 * as a window end and is not a time of day).
 */
final readonly class TimeOfDay
{
    private function __construct(
        public int $secondsFromMidnight,
    ) {}

    public static function fromString(string $time): self
    {
        if (preg_match('/\A([01]\d|2[0-3]):([0-5]\d)\z/', $time, $matches) !== 1) {
            throw new InvalidValueException("Time of day must be in HH:MM format (00:00 through 23:59): {$time}");
        }

        return new self((int) $matches[1] * 3600 + (int) $matches[2] * 60);
    }

    /**
     * The HH:MM notation paired with fromString (used by the builder).
     */
    public function toString(): string
    {
        return sprintf('%02d:%02d', intdiv($this->secondsFromMidnight, 3600), intdiv($this->secondsFromMidnight % 3600, 60));
    }
}
