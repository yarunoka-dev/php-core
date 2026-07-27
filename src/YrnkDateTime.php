<?php

namespace Yarunoka;

use Yarunoka\Exceptions\InvalidValueException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A point in time on the document timezone's clock — the value a timed
 * occurrence is answered with, and the type of a schedule's validity
 * range. YrnkDate is the all-day counterpart.
 *
 * Seconds are accepted even though no literal in the DSL can spell them:
 * the interval every (["every", N, "second"]) lands occurrences on a
 * non-zero second, and this type carries both those and the from / until
 * literals. Rejecting seconds in a document is therefore the parser's
 * job, not this type's.
 */
final class YrnkDateTime extends DateTimeImmutable
{
    public function __construct(string $dateTime, DateTimeZone $timezone)
    {
        // The parent accepts relative wording ("tomorrow"), offsets, and
        // fractional seconds, so the Yarunoka spelling is enforced here
        // before handing the string over. Seconds are optional and default
        // to zero.
        $pattern = '/\A(\d{4})-(\d{2})-(\d{2}) (?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/';

        if (preg_match($pattern, $dateTime, $matches) !== 1) {
            throw new InvalidValueException(
                "Date-time must be in \"YYYY-MM-DD HH:MM\" or \"YYYY-MM-DD HH:MM:SS\" format: {$dateTime}",
            );
        }

        if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw new InvalidValueException("Date does not exist: {$dateTime}");
        }

        parent::__construct($dateTime, $timezone);
    }
}
