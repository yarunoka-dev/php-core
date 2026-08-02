<?php

namespace Yarunoka\Docgen\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Yarunoka\Docgen\Generator;

class GeneratorTest extends TestCase
{
    private function generate(): string
    {
        return (new Generator(__DIR__ . '/fixtures'))->generate();
    }

    #[Test]
    public function every_public_declaration_in_the_tree_is_on_the_page(): void
    {
        $page = $this->generate();

        $this->assertStringContainsString('### Color', $page);
        $this->assertStringContainsString('### One', $page);
        $this->assertStringContainsString('### SorterInterface', $page);
        $this->assertStringContainsString('### Two', $page);
        $this->assertStringContainsString('### Widget', $page);
    }

    #[Test]
    public function internal_declarations_are_left_out(): void
    {
        $this->assertStringNotContainsString('Hidden', $this->generate());
    }

    #[Test]
    public function regenerating_gives_byte_identical_output(): void
    {
        $this->assertSame($this->generate(), $this->generate());
    }
}
