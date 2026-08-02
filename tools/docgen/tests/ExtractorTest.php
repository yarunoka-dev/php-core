<?php

namespace Yarunoka\Docgen\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Yarunoka\Docgen\ClassDoc;
use Yarunoka\Docgen\Extractor;

class ExtractorTest extends TestCase
{
    /** @return list<ClassDoc> */
    private function extract(string $fixture): array
    {
        return (new Extractor())->extract(__DIR__ . '/fixtures/' . $fixture);
    }

    #[Test]
    public function class_declaration_facts_are_extracted(): void
    {
        [$widget] = $this->extract('Widget.php');

        $this->assertSame('class', $widget->kind);
        $this->assertSame('Widget', $widget->shortName);
        $this->assertSame('Docgen\Fixtures\Widget', $widget->fqcn);
        $this->assertSame('Docgen\Fixtures', $widget->namespace);
        $this->assertTrue($widget->isFinal);
        $this->assertTrue($widget->isReadonly);
        $this->assertNull($widget->extends);
        $this->assertSame(['Docgen\Fixtures\SorterInterface'], $widget->implements);
        $this->assertSame('A widget that sorts itself.', $widget->docblock?->summary);
    }

    #[Test]
    public function promoted_constructor_parameter_becomes_a_property(): void
    {
        [$widget] = $this->extract('Widget.php');

        $names = array_map(fn($p) => $p->name, $widget->properties);
        $this->assertSame(['label', 'weight'], $names);
        $this->assertTrue($widget->properties[0]->promoted);
        $this->assertSame('?int', $widget->properties[1]->type);
    }

    #[Test]
    public function method_signature_carries_parameter_and_return_types(): void
    {
        [$widget] = $this->extract('Widget.php');

        $compare = array_values(array_filter($widget->methods, fn($m) => $m->name === 'compare'))[0];
        $this->assertSame('compare(Widget $a, Widget $b): int', $compare->signature);
        $this->assertSame('Compares two widgets by weight.', $compare->docblock?->summary);
    }

    #[Test]
    public function public_surface_drops_internal_and_non_public_members(): void
    {
        [$widget] = $this->extract('Widget.php');

        $surface = $widget->publicSurface();

        $this->assertSame(['DEFAULT_LABEL'], array_map(fn($c) => $c->name, $surface->constants));
        $this->assertSame(['weight'], array_map(fn($p) => $p->name, $surface->properties));
        $this->assertSame(['compare'], array_map(fn($m) => $m->name, $surface->methods));
    }

    #[Test]
    public function interface_parents_are_recorded_as_extends(): void
    {
        [$sorter] = $this->extract('SorterInterface.php');

        $this->assertSame('interface', $sorter->kind);
        $this->assertSame('Countable', $sorter->extends);
        $this->assertFalse($sorter->isFinal);
    }

    #[Test]
    public function enum_backing_type_and_cases_are_extracted(): void
    {
        [$color] = $this->extract('Color.php');

        $this->assertSame('enum', $color->kind);
        $this->assertSame('string', $color->backingType);
        $this->assertSame(['Red', 'Blue'], $color->cases);
        $this->assertSame(['label'], array_map(fn($m) => $m->name, $color->methods));
    }

    #[Test]
    public function internal_class_is_flagged(): void
    {
        [$hidden] = $this->extract('Hidden.php');

        $this->assertTrue($hidden->isInternal());
    }

    #[Test]
    public function anonymous_classes_are_not_declarations(): void
    {
        $declarations = $this->extract('edge.php');

        $this->assertSame(['One', 'Two'], array_map(fn($d) => $d->shortName, $declarations));
        $this->assertSame('Docgen\Fixtures\Edge\One', $declarations[1]->extends);
    }
}
