<?php

namespace Yarunoka\Tests\Unit\Resolvers;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Resolvers\YasumiHolidaysResolver;
use Yarunoka\Resolvers\YrnkHolidaysResolverInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YasumiHolidaysResolverTest extends TestCase
{
    #[Test]
    public function returns_the_providers_holidays_as_a_list_of_hyphenated_date_strings(): void
    {
        $resolver = new YasumiHolidaysResolver('Japan', fromYear: 2026, toYear: 2026);

        $dates = $resolver->resolve();

        $this->assertContains('2026-01-01', $dates); // New Year's Day
        $this->assertContains('2026-05-05', $dates); // Children's Day
        $this->assertContains('2026-11-23', $dates); // Labour Thanksgiving Day
    }

    #[Test]
    public function includes_both_ends_of_the_year_range(): void
    {
        $resolver = new YasumiHolidaysResolver('Japan', fromYear: 2026, toYear: 2027);

        $dates = $resolver->resolve();

        $this->assertContains('2026-01-01', $dates);
        $this->assertContains('2027-01-01', $dates);
    }

    #[Test]
    public function excludes_years_outside_the_range(): void
    {
        $resolver = new YasumiHolidaysResolver('Japan', fromYear: 2026, toYear: 2027);

        $dates = $resolver->resolve();

        $this->assertNotContains('2025-01-01', $dates); // the year before the start
        $this->assertNotContains('2028-01-01', $dates); // the year after the end
    }

    #[Test]
    public function equal_start_and_end_years_return_the_single_year(): void
    {
        $resolver = new YasumiHolidaysResolver('Japan', fromYear: 2026, toYear: 2026);

        $dates = $resolver->resolve();

        $this->assertContains('2026-01-01', $dates);
        $this->assertNotContains('2027-01-01', $dates);
    }

    #[Test]
    public function rejects_a_start_year_after_the_end_year(): void
    {
        $this->expectException(InvalidValueException::class);

        new YasumiHolidaysResolver('Japan', fromYear: 2027, toYear: 2026);
    }

    #[Test]
    public function implements_the_holidays_layer_resolver_contract(): void
    {
        $resolver = new YasumiHolidaysResolver('Japan', fromYear: 2026, toYear: 2026);

        $this->assertInstanceOf(YrnkHolidaysResolverInterface::class, $resolver);
    }
}
