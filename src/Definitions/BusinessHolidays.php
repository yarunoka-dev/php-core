<?php

namespace Yarunoka\Definitions;

use Yarunoka\Internal\DateSetDefinition;

/**
 * The organization's own closures. In the layer model, the layer above
 * holidays (a built-in definition).
 */
final class BusinessHolidays
{
    use DateSetDefinition;
}
