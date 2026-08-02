<?php

namespace Yarunoka\Docgen;

/**
 * A property as it appears in the source, whether declared conventionally
 * or promoted from a constructor parameter.
 */
final readonly class PropertyDoc
{
    public function __construct(
        public string $name,
        public string $visibility,
        public ?string $type,
        public bool $isStatic,
        public bool $isReadonly,
        public bool $promoted,
        public ?Docblock $docblock,
    ) {}
}
