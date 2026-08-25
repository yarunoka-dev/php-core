<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Internal\Parser\DuplicateMemberScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the raw-text scan that rejects duplicate member names: JSON
 * decoding quietly keeps one of the duplicates, so the scan reads the
 * document text itself. Names compare after escape resolution — JSON
 * decides member equality on the resolved characters, never on the
 * written bytes.
 */
class DuplicateMemberScannerTest extends TestCase
{
    #[Test]
    #[DataProvider('uniqueDocuments')]
    public function passes_a_document_with_unique_member_names(string $json): void
    {
        DuplicateMemberScanner::scan($json);

        $this->addToAssertionCount(1); // scanned without an exception
    }

    #[Test]
    #[DataProvider('duplicateDocuments')]
    public function rejects_a_document_writing_a_member_name_twice(string $json): void
    {
        $this->expectException(InvalidYrnkException::class);

        DuplicateMemberScanner::scan($json);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function uniqueDocuments(): array
    {
        return [
            'an empty object' => ['{}'],
            'an empty array' => ['[]'],
            'a scalar' => ['true'],
            'distinct members' => ['{"a": 1, "b": 2}'],
            'the same name in different objects' => ['[{"a": 1}, {"a": 2}]'],
            'the same name at different depths' => ['{"a": {"a": 1}}'],
            'a member value spelling braces inside a string' => ['{"a": "{\\"a\\": 1, \\"a\\": 2}", "b": 2}'],
            'an escaped quote inside a name' => ['{"a\\"": 1, "a": 2}'],
            'distinct names that share a prefix' => ['{"timezone": 1, "timezones": 2}'],
            'whitespace around everything' => [" {\n\t\"a\" : [ 1 , {\"b\": 2} ] , \"c\" : null }"],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function duplicateDocuments(): array
    {
        return [
            'the same name twice' => ['{"a": 1, "a": 2}'],
            'the same name twice with equal values' => ['{"a": 1, "a": 1}'],
            'a name colliding through a unicode escape' => ['{"timezone": 1, "\\u0074imezone": 2}'],
            'a duplicate in a nested object' => ['{"a": {"b": 1, "b": 2}}'],
            'a duplicate in an object inside an array' => ['{"a": [{"b": 1, "b": 2}]}'],
            'a duplicate past other members' => ['{"a": 1, "b": {"c": []}, "a": 2}'],
            'a duplicate empty name' => ['{"": 1, "": 2}'],
            'a digits-only name written twice' => ['{"25": 1, "25": 2}'],
        ];
    }
}
