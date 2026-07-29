<?php

namespace Yarunoka\Calendar;

use Yarunoka\Internal\DateSetDefinition;

/**
 * A named date list of the open namespace (an entry of
 * calendar.date_sets). Unlike the built-in definitions it takes no part in
 * the layers: such a name is a flat "membership in a set" and nothing
 * more. This is where a document holds the dates it names; a name whose
 * dates come from elsewhere is left to a resolver instead.
 */
final class YrnkDateSet
{
    use DateSetDefinition;
}
