<?php

namespace Yarunoka\Tests\Unit\Vocabulary;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Vocabulary\YrnkDayName;
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
        $this->assertSame(YrnkDayName::Mon, YrnkDayName::fromIsoNumber(1));
        $this->assertSame(YrnkDayName::Sun, YrnkDayName::fromIsoNumber(7));
    }

    #[Test]
    public function day_name_rejects_iso_number_above_range(): void
    {
        $this->expectException(InvalidValueException::class);

        YrnkDayName::fromIsoNumber(8);
    }

    #[Test]
    public function day_name_rejects_iso_number_below_range(): void
    {
        $this->expectException(InvalidValueException::class);

        YrnkDayName::fromIsoNumber(0);
    }

    #[Test]
    public function day_name_iso_number_pairs_with_from_iso_number(): void
    {
        $this->assertSame(1, YrnkDayName::Mon->isoNumber());
        $this->assertSame(7, YrnkDayName::Sun->isoNumber());
        $this->assertSame(YrnkDayName::Wed, YrnkDayName::fromIsoNumber(YrnkDayName::Wed->isoNumber()));
    }

    #[Test]
    public function day_name_is_weekend_only_for_saturday_and_sunday(): void
    {
        $this->assertTrue(YrnkDayName::Sat->isWeekend());
        $this->assertTrue(YrnkDayName::Sun->isWeekend());
        $this->assertFalse(YrnkDayName::Mon->isWeekend());
        $this->assertFalse(YrnkDayName::Fri->isWeekend());
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
