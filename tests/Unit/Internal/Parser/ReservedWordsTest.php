<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\ReservedNameException;
use Yarunoka\Internal\Parser\ReservedWords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReservedWordsTest extends TestCase
{
    #[Test]
    #[DataProvider('reservedNames')]
    public function rejects_reserved_words_and_literal_shapes(string $name): void
    {
        $this->expectException(ReservedNameException::class);

        ReservedWords::ensureUsable($name);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function reservedNames(): array
    {
        return [
            'calendar vocabulary' => ['holiday'],
            'window vocabulary' => ['business_hour'],
            'day name' => ['mon'],
            'ordinal word' => ['3rd'],
            'special day' => ['last_day_of_month'],
            'structural word' => ['not'],
            'unit word' => ['minute'],
            'document structural key' => ['schedules'],
            'definitions reserved key' => ['custom'],
            'digits only' => ['25'],
            'time-shaped' => ['10:00'],
            'date-shaped' => ['2026-01-01'],
            'empty string' => [''],
            'whitespace only' => ['   '],
        ];
    }

    #[Test]
    public function accepts_ordinary_names(): void
    {
        ReservedWords::ensureUsable('fête-nationale');
        ReservedWords::ensureUsable('garbage-days');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function the_reserved_words_agree_with_the_custom_name_enum_of_the_json_schema(): void
    {
        // Detects drift between the schema and the PHP duplicate (the JSON
        // Schema is the authority on the syntax).
        $enum = $this->reservedWordsInSchema('customName');

        $this->assertSame([], array_diff(ReservedWords::WORDS, $enum), 'reserved word missing from the schema');
        $this->assertSame([], array_diff($enum, ReservedWords::WORDS), 'reserved word missing from PHP');
        $this->assertSame(array_unique($enum), $enum, 'duplicate in the schema enum');
    }

    #[Test]
    public function the_day_atom_word_reserved_words_are_contained_in_the_custom_name_ones(): void
    {
        $customWords = $this->reservedWordsInSchema('customName');
        $dayAtomWords = $this->reservedWordsInSchema('dayAtomWord');

        $this->assertSame([], array_diff($dayAtomWords, $customWords));
    }

    /**
     * @return list<string>
     */
    private function reservedWordsInSchema(string $definition): array
    {
        $value = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/schema/yarunoka.schema.json'),
            associative: true,
        );

        foreach (['$defs', $definition, 'allOf', 0, 'not', 'enum'] as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                $this->fail("enum of {$definition} not found in the schema");
            }

            $value = $value[$key];
        }

        if (! is_array($value)) {
            $this->fail("enum of {$definition} in the schema is not a list");
        }

        $words = [];

        foreach ($value as $word) {
            if (! is_string($word)) {
                $this->fail("enum of {$definition} in the schema contains a non-string");
            }

            $words[] = $word;
        }

        return $words;
    }
}
