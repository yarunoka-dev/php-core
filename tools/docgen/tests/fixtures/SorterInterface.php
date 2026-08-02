<?php

namespace Docgen\Fixtures;

/**
 * Something that can order widgets.
 */
interface SorterInterface extends \Countable
{
    /**
     * Compares two widgets.
     */
    public function compare(Widget $a, Widget $b): int;
}
