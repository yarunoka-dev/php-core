<?php

namespace Yarunoka\Docgen;

/**
 * A class constant as it appears in the source.
 */
final readonly class ConstantDoc
{
    public function __construct(
        public string $name,
        public string $visibility,
        public ?Docblock $docblock,
    ) {}
}
