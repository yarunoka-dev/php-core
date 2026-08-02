<?php

namespace Yarunoka\Docgen;

/**
 * One class-like declaration (class, interface, enum or trait) with the
 * facts the reference page needs.
 */
final readonly class ClassDoc
{
    /**
     * @param 'class'|'interface'|'enum'|'trait' $kind
     * @param list<string> $implements
     * @param list<string> $cases
     * @param list<ConstantDoc> $constants
     * @param list<PropertyDoc> $properties
     * @param list<MethodDoc> $methods
     */
    public function __construct(
        public string $kind,
        public string $shortName,
        public string $fqcn,
        public string $namespace,
        public bool $isFinal,
        public bool $isAbstract,
        public bool $isReadonly,
        public ?string $extends,
        public array $implements,
        public ?string $backingType,
        public array $cases,
        public ?Docblock $docblock,
        public array $constants,
        public array $properties,
        public array $methods,
    ) {}

    public function isInternal(): bool
    {
        return $this->docblock?->hasTag('internal') ?? false;
    }

    /**
     * The declaration as the reference shows it: only public members, and
     * of those only the ones not marked @internal.
     */
    public function publicSurface(): self
    {
        $shown = fn(ConstantDoc|PropertyDoc|MethodDoc $member) => $member->visibility === 'public'
            && !($member->docblock?->hasTag('internal') ?? false);

        return new self(
            $this->kind,
            $this->shortName,
            $this->fqcn,
            $this->namespace,
            $this->isFinal,
            $this->isAbstract,
            $this->isReadonly,
            $this->extends,
            $this->implements,
            $this->backingType,
            $this->cases,
            $this->docblock,
            array_values(array_filter($this->constants, $shown)),
            array_values(array_filter($this->properties, $shown)),
            array_values(array_filter($this->methods, $shown)),
        );
    }
}
