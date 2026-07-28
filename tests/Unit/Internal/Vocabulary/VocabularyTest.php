<?php

namespace Yarunoka\Tests\Unit\Internal\Vocabulary;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Internal\Vocabulary\DayNames;
use Yarunoka\Internal\Vocabulary\Directions;
use Yarunoka\Internal\Vocabulary\Ordinals;
use Yarunoka\Internal\Vocabulary\TimeUnits;
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
        $this->assertSame(DayName::Mon, DayNames::fromIsoNumber(1));
        $this->assertSame(DayName::Sun, DayNames::fromIsoNumber(7));
    }

    #[Test]
    public function day_name_rejects_iso_number_above_range(): void
    {
        $this->expectException(InvalidValueException::class);

        DayNames::fromIsoNumber(8);
    }

    #[Test]
    public function day_name_rejects_iso_number_below_range(): void
    {
        $this->expectException(InvalidValueException::class);

        DayNames::fromIsoNumber(0);
    }

    #[Test]
    public function day_name_iso_number_pairs_with_from_iso_number(): void
    {
        $this->assertSame(1, DayNames::isoNumber(DayName::Mon));
        $this->assertSame(7, DayNames::isoNumber(DayName::Sun));
        $this->assertSame(DayName::Wed, DayNames::fromIsoNumber(DayNames::isoNumber(DayName::Wed)));
    }

    #[Test]
    public function day_name_is_weekend_only_for_saturday_and_sunday(): void
    {
        $this->assertTrue(DayNames::isWeekend(DayName::Sat));
        $this->assertTrue(DayNames::isWeekend(DayName::Sun));
        $this->assertFalse(DayNames::isWeekend(DayName::Mon));
        $this->assertFalse(DayNames::isWeekend(DayName::Fri));
    }

    #[Test]
    public function ordinal_week_index_returns_week_number_and_null_for_last(): void
    {
        $this->assertSame(1, Ordinals::weekIndex(Ordinal::First));
        $this->assertSame(5, Ordinals::weekIndex(Ordinal::Fifth));
        $this->assertNull(Ordinals::weekIndex(Ordinal::Last));
    }

    #[Test]
    public function time_unit_seconds_returns_the_length_of_the_unit(): void
    {
        $this->assertSame(3600, TimeUnits::seconds(TimeUnit::Hour));
        $this->assertSame(60, TimeUnits::seconds(TimeUnit::Minute));
        $this->assertSame(1, TimeUnits::seconds(TimeUnit::Second));
    }

    #[Test]
    public function time_unit_maximum_amount_is_how_many_of_the_unit_fit_in_a_day(): void
    {
        $this->assertSame(24, TimeUnits::maximumAmount(TimeUnit::Hour));
        $this->assertSame(1440, TimeUnits::maximumAmount(TimeUnit::Minute));
        $this->assertSame(86400, TimeUnits::maximumAmount(TimeUnit::Second));
    }

    #[Test]
    public function step_seconds_multiplies_the_amount_by_the_unit(): void
    {
        $this->assertSame(36 * 3600, TimeUnits::stepSeconds(36, TimeUnit::Hour));
        $this->assertSame(129600, TimeUnits::stepSeconds(2160, TimeUnit::Minute));
        $this->assertSame(172800, TimeUnits::stepSeconds(172800, TimeUnit::Second));
    }

    #[Test]
    public function direction_step_returns_the_increment_of_the_direction(): void
    {
        $this->assertSame(-1, Directions::step(Direction::Prev));
        $this->assertSame(1, Directions::step(Direction::Next));
    }
}
