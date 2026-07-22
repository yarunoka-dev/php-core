<?php

namespace Yarunoka\Calendar;

use Yarunoka\Internal\DateSetDefinition;

/**
 * The user's own named date list (a value of definitions.custom). Unlike
 * the built-in definitions it takes no part in the layers: a custom name
 * is a flat "membership in a set" and nothing more.
 */
final class CustomDefinition
{
    use DateSetDefinition;
}
