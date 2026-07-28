<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Internal\Parser\EverySequenceParser;
use Yarunoka\Vocabulary\TimeUnit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EverySequenceParserTest extends TestCase
{
    #[Test]
    public function parses_the_count_and_the_unit(): void
    {
        $sequence = EverySequenceParser::parse([36, 'hour']);

        $this->assertSame(36, $sequence->amount);
        $this->assertSame(TimeUnit::Hour, $sequence->unit);
    }

    #[Test]
    public function has_no_one_day_cap_unlike_the_grid(): void
    {
        $seconds = EverySequenceParser::parse([172800, 'second']); // two days

        $this->assertSame(172800, $seconds->amount);
        $this->assertSame(TimeUnit::Second, $seconds->unit);

        $minutes = EverySequenceParser::parse([2160, 'minute']); // a day and a half

        $this->assertSame(2160, $minutes->amount);
        $this->assertSame(TimeUnit::Minute, $minutes->unit);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function invalidValues(): array
    {
        return [
            'count of zero' => [[0, 'hour']],
            'string count' => [['36', 'hour']],
            'one element' => [[36]],
            'three elements' => [[36, 'hour', 'extra']],
            'object shape' => [['every' => [36, 'hour']]],
            'unit day' => [[2, 'day']],
            'plural unit' => [[2, 'hours']],
            'unknown unit' => [[2, 'week']],
            'not an array' => ['36 hour'],
        ];
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function rejects_a_malformed_value(mixed $raw): void
    {
        $this->expectException(InvalidYrnkException::class);

        EverySequenceParser::parse($raw);
    }
}
