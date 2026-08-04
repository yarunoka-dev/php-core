<?php

// The conformance kit adapter's entry point, started once per case by
// the yarunoka-test runner: one request read from stdin, one answer
// written to stdout (the kit's docs/protocol.md). The protocol lives in
// the Adapter class; this file is the process wiring around it, and what
// the class throws is adapter breakage — reason to stderr, exit non-zero.

use Yarunoka\Tests\Conformance\Adapter;

require __DIR__ . '/../../../vendor/autoload.php';

$request = json_decode((string) stream_get_contents(STDIN), associative: true);

if (! is_array($request)) {
    fwrite(STDERR, "The request must be a JSON object\n");
    exit(1);
}

try {
    $response = (new Adapter())->handle($request);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo json_encode($response, JSON_THROW_ON_ERROR), "\n";
