<?php

namespace Docgen\Fixtures;

/**
 * A palette color.
 */
enum Color: string
{
    case Red = 'red';
    case Blue = 'blue';

    /**
     * The human-readable name.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
