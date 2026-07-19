<?php

namespace Yarunoka\Definitions;

use Yarunoka\Internal\DateSetDefinition;

/**
 * The "we work this day" definition. The top layer of the layer model —
 * it overrides everything below (a built-in definition).
 */
final class BusinessDays
{
    use DateSetDefinition;
}
