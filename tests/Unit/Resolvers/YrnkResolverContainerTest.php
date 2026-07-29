<?php

namespace Yarunoka\Tests\Unit\Resolvers;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Resolvers\YrnkResolverContainer;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\YrnkDate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YrnkResolverContainerTest extends TestCase
{
    /**
     * @param  list<string>  $dates
     */
    private function resolver(array $dates = []): YrnkResolverInterface
    {
        return new class ($dates) implements YrnkResolverInterface {
            /** @param list<string> $dates */
            public function __construct(private readonly array $dates) {}

            public function resolve(YrnkDate $from, YrnkDate $through): array
            {
                return $this->dates;
            }
        };
    }

    #[Test]
    public function a_bound_name_is_found_and_hands_back_what_was_bound(): void
    {
        $container = new YrnkResolverContainer();
        $resolver = $this->resolver(['2026-01-01']);

        $container->add('company-holidays', $resolver);

        $this->assertTrue($container->has('company-holidays'));
        $this->assertSame($resolver, $container->get('company-holidays'));
    }

    #[Test]
    public function a_name_nobody_bound_is_not_found(): void
    {
        $container = new YrnkResolverContainer();

        $this->assertFalse($container->has('company-holidays'));
        $this->assertNull($container->get('company-holidays'));
    }

    #[Test]
    public function binding_the_same_name_twice_raises(): void
    {
        $container = new YrnkResolverContainer();
        $container->add('company-holidays', $this->resolver());

        $this->expectException(InvalidValueException::class);

        $container->add('company-holidays', $this->resolver());
    }

    #[Test]
    public function an_empty_or_whitespace_only_name_raises(): void
    {
        $container = new YrnkResolverContainer();

        $this->expectException(InvalidValueException::class);

        $container->add('   ', $this->resolver());
    }

    #[Test]
    public function a_date_shaped_name_raises(): void
    {
        $container = new YrnkResolverContainer();

        $this->expectException(InvalidValueException::class);

        $container->add('2026-01-01', $this->resolver());
    }

    #[Test]
    public function a_yasumi_provider_name_is_bound_from_the_start(): void
    {
        $container = new YrnkResolverContainer();

        $this->assertTrue($container->has('yasumi-Japan'));
        $this->assertInstanceOf(YrnkResolverInterface::class, $container->get('yasumi-Japan'));
    }

    #[Test]
    public function binding_a_yasumi_provider_name_raises_as_an_ordinary_duplicate(): void
    {
        $container = new YrnkResolverContainer();

        $this->expectException(InvalidValueException::class);

        $container->add('yasumi-Japan', $this->resolver());
    }

    #[Test]
    public function a_yasumi_name_whose_provider_does_not_exist_is_not_bound(): void
    {
        $container = new YrnkResolverContainer();

        $this->assertFalse($container->has('yasumi-Atlantis'));

        // So the host is free to bind it like any other name.
        $container->add('yasumi-Atlantis', $this->resolver());

        $this->assertTrue($container->has('yasumi-Atlantis'));
    }
}
