<?php

namespace Yarunoka\Schedule;

use Yarunoka\Exceptions\InvalidValueException;

/**
 * The day expression of `days` (an enumeration of atoms, combined with
 * OR). Kept in written order.
 */
final readonly class DayExpression
{
    /**
     * @var non-empty-list<DayAtomInterface>
     *
     * @internal
     */
    public array $atoms;

    /**
     * @param  list<DayAtomInterface>  $atoms  Unvalidated input. Empty or structurally duplicated enumerations violate the invariants
     *
     * @internal
     */
    public function __construct(array $atoms)
    {
        if ($atoms === []) {
            throw new InvalidValueException('Day expression enumeration cannot be empty');
        }

        $seen = [];

        foreach ($atoms as $atom) {
            // Compare the whole structure of the atom, as JSON Schema's
            // uniqueItems does.
            $key = serialize($atom);

            if (isset($seen[$key])) {
                throw new InvalidValueException('Duplicate day atom in days');
            }

            $seen[$key] = true;
        }

        $this->atoms = $atoms;
    }
}
