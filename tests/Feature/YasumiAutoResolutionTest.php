<?php

namespace Yarunoka\Tests\Feature;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\UnregisteredResolverException;
use Yarunoka\Resolvers\YrnkResolverContainer;
use Yarunoka\Tests\Support\Bindings;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkParser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A document may name a holiday source the host never bound, as long as
 * it spells a yasumi provider.
 */
class YasumiAutoResolutionTest extends TestCase
{
    private function at(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso);
    }

    private function evaluate(string $holidays, string $at, ?YrnkResolverContainer $resolvers = null): bool
    {
        $resolvers ??= new YrnkResolverContainer();
        $document = (new YrnkParser($resolvers))->parse([
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'calendar' => ['holidays' => $holidays],
            'schedules' => [['days' => ['holiday'], 'times' => ['10:00']]],
        ]);
        $evaluator = new YrnkEvaluator($document->calendar, $document->timezone, $resolvers);

        return $evaluator->matches($document->schedules[0], $this->at($at));
    }

    #[Test]
    public function a_yasumi_provider_name_resolves_without_the_host_binding_anything(): void
    {
        // 2026-01-01 is New Year's Day in Japan; 2026-01-05 is not a holiday.
        $this->assertTrue($this->evaluate('yasumi-Japan', '2026-01-01T10:00:00+09:00'));
        $this->assertFalse($this->evaluate('yasumi-Japan', '2026-01-05T10:00:00+09:00'));
    }

    #[Test]
    public function it_covers_whatever_year_the_question_reaches(): void
    {
        $this->assertTrue($this->evaluate('yasumi-Japan', '2031-01-01T10:00:00+09:00'));
    }

    #[Test]
    public function binding_the_same_name_raises_rather_than_taking_precedence(): void
    {
        $this->expectException(InvalidValueException::class);

        (new YrnkResolverContainer())->add('yasumi-Japan', Bindings::returning(['2026-01-05']));
    }

    #[Test]
    public function a_provider_yasumi_does_not_know_stays_unregistered(): void
    {
        $this->expectException(UnregisteredResolverException::class);

        $this->evaluate('yasumi-Atlantis', '2026-01-01T10:00:00+09:00');
    }

    #[Test]
    public function a_name_outside_the_convention_stays_unregistered(): void
    {
        $this->expectException(UnregisteredResolverException::class);

        $this->evaluate('jp-holidays', '2026-01-01T10:00:00+09:00');
    }
}
