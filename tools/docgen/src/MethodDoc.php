<?php

namespace Yarunoka\Docgen;

/**
 * A method as it appears in the source. The signature is pre-rendered
 * (name, typed parameters, return type) because the reference page only
 * ever shows it as one string.
 */
final readonly class MethodDoc
{
    public function __construct(
        public string $name,
        public string $visibility,
        public bool $isStatic,
        public bool $isAbstract,
        public string $signature,
        public ?Docblock $docblock,
    ) {}
}
