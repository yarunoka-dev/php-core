<?php

// Entry point for `composer docs:generate` / `composer docs:check`.
// The php-parser dependency lives in its own vendor-bin namespace.

use Yarunoka\Docgen\Generator;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor-bin/docgen/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$target = "$root/docs/reference.md";

$page = (new Generator("$root/src"))->generate();

/** @var list<string> $argv CLI arguments, reachable regardless of register_argc_argv */
$argv = $_SERVER['argv'];
if (in_array('--check', $argv, true)) {
    if (!is_file($target) || file_get_contents($target) !== $page) {
        fwrite(STDERR, "docs/reference.md is stale — run `composer docs:generate` and commit the result\n");
        exit(1);
    }
    echo "docs/reference.md is up to date\n";
    exit(0);
}

file_put_contents($target, $page);
echo "wrote docs/reference.md\n";
