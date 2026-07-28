<?php

namespace Yarunoka;

use Yarunoka\Exceptions\InvalidValueException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A whole day of the schedule — the value an all-day occurrence is
 * answered with. It stands at the start of that day on the document
 * timezone's clock, which makes it a DateTimeInterface any other library
 * accepts; YrnkDateTime is the timed counterpart, and the two are told
 * apart by type rather than by value (an all-day day and a timed point at
 * its 00:00 are distinct occurrences).
 *
 * The start of the day resolves like any other wall-clock point
 * (RFC 5545 3.3.5), so in a zone whose midnight is skipped by a
 * transition the value stands slightly later in the day, and on the rare
 * occasion that a zone skips a whole day, it stands on the day that
 * followed — that resulting day is what the value means.
 */
final class YrnkDate extends DateTimeImmutable
{
    /**
     * @internal
     */
    public function __construct(string $date, DateTimeZone $timezone)
    {
        // The parent accepts relative wording ("tomorrow") and a wide
        // range of spellings, so the Yarunoka spelling is enforced here
        // before handing the string over.
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $matches) !== 1) {
            throw new InvalidValueException("Date must be in YYYY-MM-DD format: {$date}");
        }

        if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw new InvalidValueException("Date does not exist: {$date}");
        }

        parent::__construct($date, $timezone);
    }
}
