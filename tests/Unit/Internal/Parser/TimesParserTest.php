<?php

namespace Yarunoka\Tests\Unit\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Expression\BusinessHourRef;
use Yarunoka\Expression\EveryGrid;
use Yarunoka\Expression\FixedTimes;
use Yarunoka\Internal\Parser\TimesParser;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\TimeUnit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TimesParserTest extends TestCase
{
    #[Test]
    public function a_list_becomes_fixed_times_keeping_the_written_order(): void
    {
        $times = TimesParser::parse(['12:00', '09:00']);

        $this->assertInstanceOf(FixedTimes::class, $times);
        $this->assertSame(12 * 3600, $times->times[0]->secondsFromMidnight);
        $this->assertSame(9 * 3600, $times->times[1]->secondsFromMidnight);
    }

    #[Test]
    public function an_object_becomes_an_every_grid(): void
    {
        $times = TimesParser::parse(['every' => [90, 'minute']]);

        $this->assertInstanceOf(EveryGrid::class, $times);
        $this->assertSame(90, $times->amount);
        $this->assertSame(TimeUnit::Minute, $times->unit);
        $this->assertNull($times->between);
    }

    #[Test]
    public function a_between_pair_becomes_a_time_window(): void
    {
        $times = TimesParser::parse(['every' => [1, 'hour'], 'between' => ['08:00', '20:00']]);

        $this->assertInstanceOf(EveryGrid::class, $times);
        $this->assertInstanceOf(TimeWindow::class, $times->between);
    }

    #[Test]
    public function between_business_hour_becomes_a_reference_node(): void
    {
        $times = TimesParser::parse(['every' => [1, 'hour'], 'between' => 'business_hour']);

        $this->assertInstanceOf(EveryGrid::class, $times);
        $this->assertInstanceOf(BusinessHourRef::class, $times->between);
    }

    #[Test]
    public function rejects_any_other_name_in_between(): void
    {
        $this->expectException(InvalidYrnkException::class);

        TimesParser::parse(['every' => [1, 'hour'], 'between' => 'afternoon']);
    }

    #[Test]
    public function rejects_an_object_without_every(): void
    {
        $this->expectException(InvalidYrnkException::class);

        TimesParser::parse(['between' => ['08:00', '20:00']]);
    }

    #[Test]
    public function rejects_a_count_of_zero(): void
    {
        $this->expectException(InvalidYrnkException::class);

        TimesParser::parse(['every' => [0, 'hour']]);
    }

    #[Test]
    public function rejects_a_plural_unit_word(): void
    {
        $this->expectException(InvalidYrnkException::class);

        TimesParser::parse(['every' => [2, 'hours']]);
    }

    #[Test]
    public function rejects_an_unknown_key(): void
    {
        $this->expectException(InvalidYrnkException::class);

        TimesParser::parse(['every' => [1, 'hour'], 'window' => ['08:00', '20:00']]);
    }

    #[Test]
    public function rejects_an_empty_list(): void
    {
        $this->expectException(InvalidYrnkException::class);

        TimesParser::parse([]);
    }

    #[Test]
    public function rejects_a_non_array(): void
    {
        $this->expectException(InvalidYrnkException::class);

        TimesParser::parse('09:00');
    }
}
