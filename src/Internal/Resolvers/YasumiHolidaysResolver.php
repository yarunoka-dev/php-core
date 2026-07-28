<?php

namespace Yarunoka\Internal\Resolvers;

use Yarunoka\Resolvers\YrnkHolidaysResolverInterface;
use Yarunoka\YrnkDate;
use RuntimeException;
use Yasumi\Yasumi;

/**
 * A ready-made resolver that computes the holiday list with yasumi (the
 * library default). azuyalabs/yasumi is a suggest dependency, installed
 * only when this class is used.
 *
 * The years covered follow the range asked for, so no year the evaluation
 * reaches is left out.
 *
 * @internal
 */
final readonly class YasumiHolidaysResolver implements YrnkHolidaysResolverInterface
{
    /**
     * @param  string  $provider  A yasumi provider name (e.g. 'Japan')
     */
    public function __construct(private string $provider)
    {
        if (! class_exists(Yasumi::class)) {
            throw new RuntimeException(
                'Using YasumiHolidaysResolver requires installing azuyalabs/yasumi (composer require azuyalabs/yasumi)',
            );
        }
    }

    public function resolve(YrnkDate $from, YrnkDate $to): array
    {
        $dates = [];

        for ($year = (int) $from->format('Y'); $year <= (int) $to->format('Y'); $year++) {
            foreach (Yasumi::create($this->provider, $year)->getHolidayDates() as $date) {
                $dates[] = $date;
            }
        }

        return $dates;
    }
}
