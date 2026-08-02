<?php

namespace Yarunoka\Docgen;

/**
 * A parsed docblock: prose paragraphs and @tags, stripped of comment framing.
 */
final readonly class Docblock
{
    /** @param list<string> $tags */
    private function __construct(
        public string $summary,
        public string $text,
        public array $tags,
    ) {}

    public static function parse(string $raw): self
    {
        // Strip the /** ... */ framing first so single-line docblocks work too,
        // then the leading asterisk of each line
        $body = preg_replace('~^/\*\*|\*/$~', '', trim($raw)) ?? '';
        $lines = array_map(
            fn(string $line) => trim((string) preg_replace('~^\s*\*\s?~', '', $line)),
            explode("\n", $body),
        );

        $paragraphs = [];
        $paragraph = [];
        $tags = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '@')) {
                $tags[] = $line;
                continue;
            }
            if ($tags !== []) {
                // A non-tag line after a tag continues that tag (wrapped tag prose)
                if ($line !== '') {
                    $tags[count($tags) - 1] .= ' ' . $line;
                }
                continue;
            }
            if ($line === '') {
                if ($paragraph !== []) {
                    $paragraphs[] = implode(' ', $paragraph);
                    $paragraph = [];
                }
                continue;
            }
            $paragraph[] = $line;
        }
        if ($paragraph !== []) {
            $paragraphs[] = implode(' ', $paragraph);
        }

        return new self(
            $paragraphs[0] ?? '',
            implode("\n\n", $paragraphs),
            $tags,
        );
    }

    public function hasTag(string $name): bool
    {
        foreach ($this->tags as $tag) {
            if (preg_match('/^@' . preg_quote($name, '/') . '(?![\w-])/', $tag) === 1) {
                return true;
            }
        }

        return false;
    }
}
