<?php

namespace Yarunoka\Definitions;

use Yarunoka\Internal\DateSetDefinition;

/**
 * The holidays definition — public holidays. In the layer model, the
 * "closed by default" layer (a built-in definition).
 */
final class Holidays
{
    use DateSetDefinition;
}
