<?php

namespace Yarunoka\Time;

use Yarunoka\Exceptions\InvalidValueException;

/**
 * A time window as the half-open interval [start, end). Only the end
 * accepts "24:00" (= 86400 seconds) as the end of the day. Windows crossing
 * midnight (start >= end) cannot be expressed.
 */
final readonly class TimeWindow
{
    public const int END_OF_DAY_SECONDS = 86_400;

    private function __construct(
        public int $startSeconds,
        public int $endSeconds,
    ) {}

    public static function fromStrings(string $start, string $end): self
    {
        $startSeconds = TimeOfDay::fromString($start)->secondsFromMidnight;
        $endSeconds = $end === '24:00'
            ? self::END_OF_DAY_SECONDS
            : TimeOfDay::fromString($end)->secondsFromMidnight;

        if ($startSeconds >= $endSeconds) {
            throw new InvalidValueException("Time window requires start < end (crossing midnight is not supported): [{$start}, {$end}]");
        }

        return new self($startSeconds, $endSeconds);
    }

    /**
     * The [HH:MM, HH:MM] notation paired with fromStrings (used by the
     * builder). An end at the end of the day becomes "24:00".
     *
     * @return array{string, string}
     */
    public function toStrings(): array
    {
        $format = static fn (int $seconds): string => sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));

        return [
            $format($this->startSeconds),
            $this->endSeconds === self::END_OF_DAY_SECONDS ? '24:00' : $format($this->endSeconds),
        ];
    }
}
