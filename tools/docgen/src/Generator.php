<?php

namespace Yarunoka\Docgen;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Walks a source tree and produces the reference page for its public
 * surface: every class-like declaration not tagged @internal, trimmed to
 * its public, non-@internal members.
 */
final class Generator
{
    public function __construct(private readonly string $srcDir) {}

    public function generate(): string
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->srcDir));
        foreach ($iterator as $info) {
            if ($info instanceof \SplFileInfo && $info->isFile() && $info->getExtension() === 'php') {
                $files[] = $info->getPathname();
            }
        }
        sort($files, SORT_STRING); // deterministic walk order

        $extractor = new Extractor();
        $docs = [];
        foreach ($files as $file) {
            foreach ($extractor->extract($file) as $doc) {
                if (!$doc->isInternal()) {
                    $docs[] = $doc->publicSurface();
                }
            }
        }

        return (new Renderer())->render($docs);
    }
}
