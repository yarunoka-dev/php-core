<?php

namespace Yarunoka\Tests\Unit\Internal\Evaluation;

use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkHolidays;
use Yarunoka\Internal\Evaluation\ResolvedCalendar;
use Yarunoka\YrnkDate;
use DateTimeZone;
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
        $ranges = [];
        $resolved = new ResolvedCalendar(
            new YrnkCalendar(holidays: YrnkHolidays::byResolver('recording')),
            resolvers: ['recording' => function (YrnkDate $from, YrnkDate $through) use (&$ranges): array {
                $ranges[] = [$from->format('Y-m-d'), $through->format('Y-m-d')];

                return ['2026-01-01'];
            }],
            timezone: self::utc(),
        );

        $resolved->holidayContains(new YrnkDate('2026-05-05', self::utc()));

        $this->assertSame([['2026-01-01', '2026-12-31']], $ranges);
    }

    #[Test]
    public function a_year_already_resolved_is_not_asked_for_again(): void
    {
        $calls = 0;
        $resolved = $this->recording($calls);

        $resolved->holidayContains(new YrnkDate('2026-01-01', self::utc()));
        $resolved->holidayContains(new YrnkDate('2026-12-31', self::utc()));

        $this->assertSame(1, $calls);
    }

    #[Test]
    public function a_day_in_another_year_is_resolved_on_its_own(): void
    {
        $calls = 0;
        $resolved = $this->recording($calls);

        $resolved->holidayContains(new YrnkDate('2026-06-01', self::utc()));
        $resolved->holidayContains(new YrnkDate('2027-06-01', self::utc()));

        $this->assertSame(2, $calls);
    }

    #[Test]
    public function a_resolver_contract_instance_is_given_the_range_too(): void
    {
        $resolver = new RecordingResolver();
        $resolved = new ResolvedCalendar(
            new YrnkCalendar(holidays: YrnkHolidays::byResolver('jp')),
            resolvers: ['jp' => $resolver],
            timezone: self::utc(),
        );

        $resolved->holidayContains(new YrnkDate('2026-05-05', self::utc()));

        $this->assertSame([['2026-01-01', '2026-12-31']], $resolver->ranges);
    }

    #[Test]
    public function a_fixed_date_list_is_not_affected_by_the_range(): void
    {
        $resolved = new ResolvedCalendar(
            new YrnkCalendar(holidays: YrnkHolidays::ofDates(['2026-01-01', '2027-01-01'], self::utc())),
            resolvers: [],
            timezone: self::utc(),
        );

        $this->assertTrue($resolved->holidayContains(new YrnkDate('2026-01-01', self::utc())));
        $this->assertTrue($resolved->holidayContains(new YrnkDate('2027-01-01', self::utc())));
    }

    private function recording(int &$calls): ResolvedCalendar
    {
        return new ResolvedCalendar(
            new YrnkCalendar(holidays: YrnkHolidays::byResolver('counting')),
            resolvers: ['counting' => function (YrnkDate $from, YrnkDate $through) use (&$calls): array {
                $calls++;

                return [$from->format('Y') . '-01-01'];
            }],
            timezone: self::utc(),
        );
    }
}

/**
 * A resolver contract implementation that records the ranges it is asked
 * for.
 */
final class RecordingResolver implements \Yarunoka\Resolvers\YrnkResolverInterface
{
    /** @var list<array{string, string}> */
    public array $ranges = [];

    public function resolve(YrnkDate $from, YrnkDate $through): array
    {
        $this->ranges[] = [$from->format('Y-m-d'), $through->format('Y-m-d')];

        return [$from->format('Y') . '-01-01'];
    }
}
