<?php

namespace Yarunoka\Resolvers;

use Yarunoka\Exceptions\InvalidValueException;
use RuntimeException;
use Yasumi\Yasumi;

/**
 * A ready-made resolver that computes the holiday list with yasumi (the
 * library default). azuyalabs/yasumi is a suggest dependency, installed
 * only when this class is used.
 *
 * The caller chooses the year range so that it covers what is being
 * evaluated — years outside the range are silently treated as "no
 * holidays", so the range must always cover the years the evaluation
 * reaches.
 */
final readonly class YasumiHolidaysResolver implements YrnkHolidaysResolverInterface
{
    /**
     * @param  string  $provider  A yasumi provider name (e.g. 'Japan')
     */
    public function __construct(
        private string $provider,
        private int $fromYear,
        private int $toYear,
    ) {
        if (! class_exists(Yasumi::class)) {
            throw new RuntimeException(
                'Using YasumiHolidaysResolver requires installing azuyalabs/yasumi (composer require azuyalabs/yasumi)',
            );
        }

        if ($fromYear > $toYear) {
            throw new InvalidValueException(
                "Start of the year range must not be after its end: {$fromYear}-{$toYear}",
            );
        }
    }

    public function resolve(): array
    {
        $dates = [];

        for ($year = $this->fromYear; $year <= $this->toYear; $year++) {
            foreach (Yasumi::create($this->provider, $year)->getHolidayDates() as $date) {
                $dates[] = $date;
            }
        }

        return $dates;
    }
}
