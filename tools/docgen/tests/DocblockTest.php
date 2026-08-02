<?php

namespace Yarunoka\Docgen\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Yarunoka\Docgen\Docblock;

class DocblockTest extends TestCase
{
    #[Test]
    public function summary_is_the_first_paragraph_joined_into_one_line(): void
    {
        $docblock = Docblock::parse(<<<'DOC'
            /**
             * Checks schedules against the definitions and validates that
             * every reference resolves.
             *
             * Second paragraph with more detail.
             */
            DOC);

        $this->assertSame(
            'Checks schedules against the definitions and validates that every reference resolves.',
            $docblock->summary,
        );
    }

    #[Test]
    public function text_keeps_every_prose_paragraph(): void
    {
        $docblock = Docblock::parse(<<<'DOC'
            /**
             * First paragraph.
             *
             * Second paragraph
             * spanning two lines.
             */
            DOC);

        $this->assertSame("First paragraph.\n\nSecond paragraph spanning two lines.", $docblock->text);
    }

    #[Test]
    public function single_line_docblock_yields_its_tag(): void
    {
        $docblock = Docblock::parse('/** @internal */');

        $this->assertSame('', $docblock->summary);
        $this->assertTrue($docblock->hasTag('internal'));
    }

    #[Test]
    public function has_tag_matches_the_whole_tag_name_only(): void
    {
        $docblock = Docblock::parse(<<<'DOC'
            /**
             * @internally-named thing
             */
            DOC);

        $this->assertFalse($docblock->hasTag('internal'));
    }

    #[Test]
    public function tag_continuation_lines_join_their_tag(): void
    {
        $docblock = Docblock::parse(<<<'DOC'
            /**
             * Summary.
             *
             * @param string $name the name of the schedule,
             *   as written in the document
             */
            DOC);

        $this->assertSame(
            ['@param string $name the name of the schedule, as written in the document'],
            $docblock->tags,
        );
    }

    #[Test]
    public function prose_after_tags_does_not_leak_into_text(): void
    {
        $docblock = Docblock::parse(<<<'DOC'
            /**
             * Summary.
             *
             * @internal
             */
            DOC);

        $this->assertSame('Summary.', $docblock->text);
        $this->assertTrue($docblock->hasTag('internal'));
    }
}
