<?php

namespace Yarunoka\Schedule;

use Yarunoka\Exceptions\InvalidValueException;

/**
 * A reference atom to a custom definition (the user's own named date
 * list). The reference is a self-contained value; that the referent exists
 * is validated by the holder of the definitions (YrnkParser /
 * YrnkEvaluator).
 */
final readonly class CustomRef implements DayAtomInterface
{
    /**
     * @internal
     */
    public function __construct(
        /** @internal */
        public string $name,
    ) {
        if (preg_match('/\\S/u', $name) !== 1) {
            throw new InvalidValueException('Custom definition reference name cannot be empty or whitespace only');
        }
    }
}
