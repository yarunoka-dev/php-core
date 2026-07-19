<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Internal\Parser\DayCycleParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DayCycleParserTest extends TestCase
{
    #[Test]
    public function parses_a_three_element_tuple(): void
    {
        $atom = DayCycleParser::parse(['every', 2, 'day']);

        $this->assertSame(2, $atom->intervalDays);
    }

    #[Test]
    public function a_count_of_one_is_legal_as_every_day(): void
    {
        $this->assertSame(1, DayCycleParser::parse(['every', 1, 'day'])->intervalDays);
    }

    #[Test]
    public function the_count_has_no_upper_bound(): void
    {
        $this->assertSame(20000, DayCycleParser::parse(['every', 20000, 'day'])->intervalDays);
    }

    /**
     * @return array<string, list<array<mixed>>>
     */
    public static function invalidTuples(): array
    {
        return [
            'two elements (omitted unit)' => [['every', 2]],
            'four elements' => [['every', 2, 'day', 'extra']],
            'count of zero' => [['every', 0, 'day']],
            'negative count' => [['every', -2, 'day']],
            'string count' => [['every', '2', 'day']],
            'fractional count' => [['every', 1.5, 'day']],
            'unit hour' => [['every', 2, 'hour']],
            'unit week' => [['every', 2, 'week']],
            'plural unit' => [['every', 2, 'days']],
        ];
    }

    /**
     * @param  array<mixed>  $raw
     */
    #[Test]
    #[DataProvider('invalidTuples')]
    public function rejects_a_malformed_tuple(array $raw): void
    {
        $this->expectException(InvalidYrnkException::class);

        DayCycleParser::parse($raw);
    }
}
