<?php

namespace Yarunoka\Tests\Unit\Internal\Builder;

use Yarunoka\Definitions\BusinessHours;
use Yarunoka\Definitions\CustomDefinition;
use Yarunoka\Definitions\Definitions;
use Yarunoka\Definitions\Holidays;
use Yarunoka\Definitions\Workweek;
use Yarunoka\Exceptions\InvalidCalendarDataException;
use Yarunoka\Internal\Builder\DefinitionsBuilder;
use Yarunoka\Time\TimeWindow;
use Yarunoka\Vocabulary\DayName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DefinitionsBuilderTest extends TestCase
{
    #[Test]
    public function builds_each_definition_into_its_raw_dsl_shape_omitting_null_keys(): void
    {
        $definitions = new Definitions(
            holidays: Holidays::ofDates(['2026-01-01']),
            workweek: new Workweek([DayName::Tue, DayName::Sat]),
            businessHours: new BusinessHours([TimeWindow::fromStrings('09:00', '18:00')]),
            custom: ['founding-day' => CustomDefinition::ofDates(['2026-10-01'])],
        );

        $this->assertSame([
            'holidays' => ['2026-01-01'],
            'workweek' => ['tue', 'sat'],
            'business_hours' => [['09:00', '18:00']],
            'custom' => ['founding-day' => ['2026-10-01']],
        ], DefinitionsBuilder::build($definitions));
    }

    #[Test]
    public function empty_definitions_become_empty(): void
    {
        $this->assertSame([], DefinitionsBuilder::build(new Definitions));
    }

    #[Test]
    public function a_resolver_name_reference_comes_out_as_the_name_itself(): void
    {
        $definitions = new Definitions(holidays: Holidays::byResolver('yasumi-jp'));

        $this->assertSame(['holidays' => 'yasumi-jp'], DefinitionsBuilder::build($definitions));
    }

    #[Test]
    public function deferred_becomes_a_resolved_snapshot(): void
    {
        $definitions = new Definitions(
            holidays: Holidays::deferred(static fn (): array => ['2026-01-01']),
        );

        $this->assertSame(['holidays' => ['2026-01-01']], DefinitionsBuilder::build($definitions));
    }

    #[Test]
    public function a_contract_violation_of_deferred_raises(): void
    {
        $definitions = new Definitions(
            holidays: Holidays::deferred(static fn (): array => ['2026/01/01']),
        );

        $this->expectException(InvalidCalendarDataException::class);

        DefinitionsBuilder::build($definitions);
    }

    #[Test]
    public function deferred_returning_a_non_array_raises_too(): void
    {
        $definitions = new Definitions(
            holidays: Holidays::deferred(static fn (): string => 'not-an-array'),
        );

        $this->expectException(InvalidCalendarDataException::class);

        DefinitionsBuilder::build($definitions);
    }
}
