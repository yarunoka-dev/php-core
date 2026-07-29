<?php

namespace Yarunoka\Tests\Unit\Internal\Evaluation;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkHolidays;
use Yarunoka\Internal\Evaluation\ResolvedCalendar;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\YrnkDate;
use DateTimeZone;
use Yarunoka\Tests\Support\Bindings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What a resolver is told when it is asked to resolve, and how long the
 * answer is kept.
 */
class ResolverRangeTest extends TestCase
{
    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }

    #[Test]
    public function a_resolver_is_given_the_range_it_should_cover(): void
    {
        $resolver = new RecordingResolver();
        $resolved = $this->holidaysFrom($resolver);

        $resolved->holidayContains(new YrnkDate('2026-05-05', self::utc()));

        $this->assertSame([['2026-01-01', '2026-12-31']], $resolver->ranges);
    }

    #[Test]
    public function a_year_already_resolved_is_not_asked_for_again(): void
    {
        $resolver = new RecordingResolver();
        $resolved = $this->holidaysFrom($resolver);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
        $resolved->holidayContains(new YrnkDate('2026-12-31', self::utc()));

        $this->assertCount(1, $resolver->ranges);
    }

    #[Test]
    public function a_day_in_another_year_is_resolved_on_its_own(): void
    {
        $resolver = new RecordingResolver();
        $resolved = $this->holidaysFrom($resolver);

        $resolved->holidayContains(new YrnkDate('2026-06-01', self::utc()));
        $resolved->holidayContains(new YrnkDate('2027-06-01', self::utc()));

        $this->assertSame(
            [['2026-01-01', '2026-12-31'], ['2027-01-01', '2027-12-31']],
            $resolver->ranges,
        );
    }

    #[Test]
    public function a_fixed_date_list_is_not_affected_by_the_range(): void
    {
        $resolved = new ResolvedCalendar(
            new YrnkCalendar(holidays: YrnkHolidays::ofDates(['2026-01-01', '2027-01-01'], self::utc())),
            timezone: self::utc(),
        );

        $this->assertTrue($resolved->holidayContains(new YrnkDate('2026-01-01', self::utc())));
        $this->assertTrue($resolved->holidayContains(new YrnkDate('2027-01-01', self::utc())));
    }

    private function holidaysFrom(YrnkResolverInterface $resolver): ResolvedCalendar
    {
        return new ResolvedCalendar(
            new YrnkCalendar(
                holidays: YrnkHolidays::byResolver('jp'),
                resolvers: Bindings::of(['jp' => $resolver]),
            ),
            timezone: self::utc(),
        );
    }
}

/**
 * A resolver contract implementation that records the ranges it is asked
 * for.
 */
final class RecordingResolver implements YrnkResolverInterface
{
    /** @var list<array{string, string}> */
    public array $ranges = [];

    public function resolve(YrnkDate $from, YrnkDate $through): array
    {
        $this->ranges[] = [$from->format('Y-m-d'), $through->format('Y-m-d')];

        return [$from->format('Y') . '-01-01'];
    }
}
