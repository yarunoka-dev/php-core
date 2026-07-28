<?php

namespace Yarunoka\Tests\Unit\Vocabulary;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Vocabulary\DayName;
use Yarunoka\Vocabulary\Direction;
use Yarunoka\Vocabulary\Ordinal;
use Yarunoka\Vocabulary\TimeUnit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VocabularyTest extends TestCase
{
    #[Test]
    public function day_name_is_lookupable_by_iso_number(): void
    {
        $this->assertSame(DayName::Mon, DayName::fromIsoNumber(1));
        $this->assertSame(DayName::Sun, DayName::fromIsoNumber(7));
    }

    #[Test]
    public function day_name_rejects_iso_number_above_range(): void
    {
        $this->expectException(InvalidValueException::class);

        DayName::fromIsoNumber(8);
    }

    #[Test]
    public function day_name_rejects_iso_number_below_range(): void
    {
        $this->expectException(InvalidValueException::class);

        DayName::fromIsoNumber(0);
    }

    #[Test]
    public function day_name_iso_number_pairs_with_from_iso_number(): void
    {
        $this->assertSame(1, DayName::Mon->isoNumber());
        $this->assertSame(7, DayName::Sun->isoNumber());
        $this->assertSame(DayName::Wed, DayName::fromIsoNumber(DayName::Wed->isoNumber()));
    }

    #[Test]
    public function day_name_is_weekend_only_for_saturday_and_sunday(): void
    {
        $this->assertTrue(DayName::Sat->isWeekend());
        $this->assertTrue(DayName::Sun->isWeekend());
        $this->assertFalse(DayName::Mon->isWeekend());
        $this->assertFalse(DayName::Fri->isWeekend());
    }

    #[Test]
    public function ordinal_week_index_returns_week_number_and_null_for_last(): void
    {
        $this->assertSame(1, Ordinal::First->weekIndex());
        $this->assertSame(5, Ordinal::Fifth->weekIndex());
        $this->assertNull(Ordinal::Last->weekIndex());
    }

    #[Test]
    public function time_unit_seconds_returns_the_length_of_the_unit(): void
    {
        $this->assertSame(3600, TimeUnit::Hour->seconds());
        $this->assertSame(60, TimeUnit::Minute->seconds());
        $this->assertSame(1, TimeUnit::Second->seconds());
    }

    #[Test]
    public function direction_step_returns_the_increment_of_the_direction(): void
    {
        $this->assertSame(-1, Direction::Prev->step());
        $this->assertSame(1, Direction::Next->step());
    }
}
