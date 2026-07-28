<?php

namespace Yarunoka\Tests\Unit\Internal\Resolvers;

use Yarunoka\Internal\Resolvers\YasumiHolidaysResolver;
use Yarunoka\Resolvers\YrnkHolidaysResolverInterface;
use Yarunoka\YrnkDate;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YasumiHolidaysResolverTest extends TestCase
{
    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }

    private static function date(string $date): YrnkDate
    {
        return new YrnkDate($date, self::utc());
    }

    #[Test]
    public function returns_the_providers_holidays_as_a_list_of_hyphenated_date_strings(): void
    {
        $resolver = new YasumiHolidaysResolver('Japan');

        $dates = $resolver->resolve(self::date('2026-01-01'), self::date('2026-12-31'));

        $this->assertContains('2026-01-01', $dates); // New Year's Day
        $this->assertContains('2026-05-05', $dates); // Children's Day
        $this->assertContains('2026-11-23', $dates); // Labour Thanksgiving Day
    }

    #[Test]
    public function covers_every_year_the_range_touches(): void
    {
        $resolver = new YasumiHolidaysResolver('Japan');

        $dates = $resolver->resolve(self::date('2026-06-01'), self::date('2027-06-01'));

        $this->assertContains('2026-01-01', $dates);
        $this->assertContains('2027-01-01', $dates);
    }

    #[Test]
    public function excludes_years_the_range_does_not_touch(): void
    {
        $resolver = new YasumiHolidaysResolver('Japan');

        $dates = $resolver->resolve(self::date('2026-01-01'), self::date('2027-12-31'));

        $this->assertNotContains('2025-01-01', $dates); // the year before the start
        $this->assertNotContains('2028-01-01', $dates); // the year after the end
    }

    #[Test]
    public function a_range_within_a_single_year_returns_that_year(): void
    {
        $resolver = new YasumiHolidaysResolver('Japan');

        $dates = $resolver->resolve(self::date('2026-03-01'), self::date('2026-03-31'));

        $this->assertContains('2026-01-01', $dates);
        $this->assertNotContains('2027-01-01', $dates);
    }

    #[Test]
    public function implements_the_holidays_layer_resolver_contract(): void
    {
        $resolver = new YasumiHolidaysResolver('Japan');

        $this->assertInstanceOf(YrnkHolidaysResolverInterface::class, $resolver);
    }
}
