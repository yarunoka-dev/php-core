<?php

namespace Docgen\Fixtures\Edge;

/**
 * First declaration in a multi-declaration file.
 */
class One
{
    public function make(): object
    {
        return new class {
            public function x(): void {}
        };
    }
}

/**
 * Second declaration in the same file.
 */
abstract class Two extends One {}
