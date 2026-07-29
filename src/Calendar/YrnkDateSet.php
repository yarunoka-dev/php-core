<?php

namespace Yarunoka\Calendar;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\YrnkDate;
use DateTimeInterface;
use DateTimeZone;

/**
 * A written date list, and the base of every kind of one. On its own it is
 * a named date list of the open namespace (an entry of
 * calendar.date_sets): a flat "membership in a set" that takes no part in
 * the layers. The built-in definitions extend it with the layer meaning
 * their key carries, and nothing else separates them — a date set is a
 * date set, whichever position holds it.
 *
 * The subclasses exist so a position can refuse the wrong one: a calendar
 * takes YrnkHolidaysDateSet where it means holidays, and no amount of
 * shared implementation lets the business_days list land there.
 *
 * This is where a document holds the dates it names. A name whose dates
 * come from elsewhere is left to a resolver instead, and is written as
 * the name — that form needs no type of its own.
 */
class YrnkDateSet
{
    /** @var list<YrnkDate> */
    public readonly array $dates;

    /**
     * Final so that every kind of date set is built the one way, which is
     * also what lets ofDates() hand back the subclass it was called on.
     * Protected rather than private because a private constructor a
     * subclass could replace would not be that guarantee.
     *
     * @param  list<YrnkDate>  $dates
     */
    final protected function __construct(array $dates)
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
     *
     * @internal
     */
    public static function ofDates(array $dates, DateTimeZone $timezone): static
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

        return new static($parsed);
    }
}
