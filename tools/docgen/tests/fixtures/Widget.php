<?php

namespace Docgen\Fixtures;

/**
 * A widget that sorts itself.
 *
 * Widgets exist only to exercise the extractor.
 */
final readonly class Widget implements SorterInterface
{
    /** The label used when none is given. */
    public const DEFAULT_LABEL = 'widget';

    /** @internal */
    public const SECRET = 's';

    /**
     * @internal
     */
    public function __construct(
        /** @internal */
        public string $label,
        public ?int $weight = null,
    ) {}

    /**
     * Compares two widgets by weight.
     */
    public function compare(Widget $a, Widget $b): int
    {
        return 0;
    }

    private function helper(): void {}
}
