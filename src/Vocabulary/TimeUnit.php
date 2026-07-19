<?php

namespace Yarunoka\Vocabulary;

/**
 * The unit word of `every`. Singular form only — in a machine-read document,
 * "either is fine" is nothing but noise for diffs, validation, and
 * cross-implementation compatibility.
 */
enum TimeUnit: string
{
    case Hour = 'hour';
    case Minute = 'minute';
    case Second = 'second';

    public function seconds(): int
    {
        return match ($this) {
            self::Hour => 3600,
            self::Minute => 60,
            self::Second => 1,
        };
    }

    public function maximumAmount(): int
    {
        return intdiv(86400, $this->seconds());
    }
}
