<?php

namespace Yarunoka\Internal;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\YrnkDate;
use DateTimeInterface;
use DateTimeZone;

/**
 * The shared implementation of a written date list. The public types with
 * meaning (YrnkHolidays / YrnkBusinessHolidays / YrnkBusinessDays / YrnkDateSet)
 * use this. A trait so that the types stay separate while the
 * implementation is shared; the public contract lives on each class.
 *
 * The other form a date-list position accepts — a name — is written as
 * the name, so it needs no type of its own.
 *
 * @internal
 */
trait DateSetDefinition
{
    /** @var list<YrnkDate> */
    public readonly array $dates;

    /**
     * @param  list<YrnkDate>  $dates
     */
    private function __construct(array $dates)
    {
        $this->dates = $dates;
    }

    /**
     * A fixed date list. A string is validated as zero-padded YYYY-MM-DD;
     * a date-time object is read as the day it spells, whatever zone it
     * happens to carry — an application keeps its holidays as dates, not
     * as instants, and converting one would silently move a day across a
     * boundary.
     *
     * Either form lands on the document's clock: a definition holds
     * wall-clock dates and declares no zone of its own, so it is handed
     * the document's when the document is read.
     *
     * @param  list<DateTimeInterface|string>  $dates
     */
    public static function ofDates(array $dates, DateTimeZone $timezone): self
    {
        $parsed = array_map(
            static fn(DateTimeInterface|string $date): YrnkDate => new YrnkDate(
                $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date,
                $timezone,
            ),
            $dates,
        );
        $seen = [];

        foreach ($parsed as $date) {
            $key = $date->format('Y-m-d');

            if (isset($seen[$key])) {
                throw new InvalidValueException("Duplicate date in date list: {$key}");
            }

            $seen[$key] = true;
        }

        return new self($parsed);
    }
}
