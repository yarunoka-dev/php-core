<?php

namespace Yarunoka\Tests\Conformance;

use Yarunoka\Exceptions\ExceptionInterface;
use Yarunoka\Exceptions\MalformedQueryException;
use Yarunoka\Resolvers\YrnkResolverContainer;
use Yarunoka\YrnkBuilder;
use Yarunoka\YrnkDate;
use Yarunoka\YrnkDateTime;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkParser;
use DateTimeImmutable;
use RuntimeException;

/**
 * Answers one request of the conformance kit's adapter protocol (the
 * kit's docs/protocol.md). Thin wiring by principle: the document and
 * the bindings go to the implementation unvalidated and unmodified, so
 * that a case carrying broken input reaches what it is aimed at. What
 * the implementation throws at is answered invalid; what only a broken
 * runner would send — an unknown query type, an envelope shape outside
 * the protocol — is breakage and throws.
 *
 * An emit request is answered by YrnkBuilder off the parsed document.
 * The evaluator's questions are per schedule, so the top-level OR is
 * composed here: any for the judgments, a merge for the enumeration.
 */
final class Adapter
{
    /**
     * @param  array<mixed>  $request
     * @return array<string, mixed>
     */
    public function handle(array $request): array
    {
        // The protocol delivers the document as a JSON string, so that
        // duplicate member names and escape spellings survive to the
        // implementation's parse. Handed over undecoded for the same
        // reason.
        $document = $request['document'] ?? null;

        if (! is_string($document)) {
            throw new RuntimeException('The request carries no document (a JSON string)');
        }

        try {
            // Registering a binding can already throw (the container
            // rejects unusable names), and that is the implementation
            // rejecting — an invalid answer, not breakage.
            $container = new YrnkResolverContainer();

            foreach ($this->bindings($request) as $name => $dates) {
                $container->add($name, new StaticResolver($dates));
            }

            $parsed = (new YrnkParser($container))->parse($document);
        } catch (ExceptionInterface) {
            return ['invalid' => true];
        }

        if (($request['action'] ?? null) === 'emit') {
            return ['document' => (new YrnkBuilder())->build($parsed)];
        }

        $query = $request['query'] ?? null;

        if (! is_array($query)) {
            throw new RuntimeException('An eval request carries a query');
        }

        $evaluator = YrnkEvaluator::fromYrnk($parsed);

        try {
            return match ($query['type'] ?? null) {
                'point' => ['result' => $this->matchesAny(
                    $evaluator,
                    $parsed->schedules,
                    $this->instant($query, 'at'),
                )],
                'period' => ['result' => $this->hasMatchInAny(
                    $evaluator,
                    $parsed->schedules,
                    $this->instant($query, 'after'),
                    $this->instant($query, 'through'),
                )],
                'enumeration' => ['result' => $this->enumerate(
                    $evaluator,
                    $parsed->schedules,
                    $this->instant($query, 'from'),
                    $this->instant($query, 'through'),
                )],
                default => throw new RuntimeException('Unknown query type'),
            };
        } catch (MalformedQueryException) {
            // The document is fine; the question is the side that does
            // not stand — a normal answer, distinct from invalid.
            return ['malformed' => true];
        } catch (ExceptionInterface) {
            return ['invalid' => true];
        }
    }

    /**
     * @param  list<\Yarunoka\YrnkSchedule>  $schedules
     */
    private function matchesAny(YrnkEvaluator $evaluator, array $schedules, DateTimeImmutable $at): bool
    {
        foreach ($schedules as $schedule) {
            if ($evaluator->matches($schedule, $at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<\Yarunoka\YrnkSchedule>  $schedules
     */
    private function hasMatchInAny(
        YrnkEvaluator $evaluator,
        array $schedules,
        DateTimeImmutable $after,
        DateTimeImmutable $through,
    ): bool {
        foreach ($schedules as $schedule) {
            if ($evaluator->hasMatchIn($schedule, $after, $through)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<\Yarunoka\YrnkSchedule>  $schedules
     * @return list<string>
     */
    private function enumerate(
        YrnkEvaluator $evaluator,
        array $schedules,
        DateTimeImmutable $from,
        DateTimeImmutable $through,
    ): array {
        // The union answers each occurrence once, deduplicated within a
        // kind: an all-day occurrence by its day, a timed one by its
        // instant. The kinds never merge.
        $occurrences = [];

        foreach ($schedules as $schedule) {
            foreach ($evaluator->occurrencesIn($schedule, $from, $through) as $occurrence) {
                $key = $occurrence instanceof YrnkDate
                    ? 'd:' . $occurrence->format('Y-m-d')
                    : 't:' . $occurrence->getTimestamp();
                $occurrences[$key] = $occurrence;
            }
        }

        $merged = array_values($occurrences);

        // Ascending; an all-day occurrence takes the start of its day as
        // its place in the order and precedes a timed point at the same
        // instant.
        usort($merged, function (YrnkDate|YrnkDateTime $a, YrnkDate|YrnkDateTime $b): int {
            $byInstant = $a->getTimestamp() <=> $b->getTimestamp();

            if ($byInstant !== 0) {
                return $byInstant;
            }

            return ($a instanceof YrnkDate ? 0 : 1) <=> ($b instanceof YrnkDate ? 0 : 1);
        });

        return array_map(
            static fn(YrnkDate|YrnkDateTime $occurrence): string => $occurrence instanceof YrnkDate
                ? $occurrence->format('Y-m-d')
                : $occurrence->format('Y-m-d\TH:i:sP'),
            $merged,
        );
    }

    /**
     * The bindings, held to the envelope shape of the protocol — a map of
     * resolver name to a list of date literals. The shape is the runner's
     * promise, so a request outside it is breakage rather than an invalid
     * answer; the literals themselves stay unvalidated on their way in.
     *
     * @param  array<mixed>  $request
     * @return array<string, list<string>>
     */
    private function bindings(array $request): array
    {
        $raw = $request['bindings'] ?? [];

        if (! is_array($raw)) {
            throw new RuntimeException('bindings must map resolver names to date lists');
        }

        $bindings = [];

        foreach ($raw as $name => $dates) {
            if (! is_string($name) || ! is_array($dates)) {
                throw new RuntimeException('bindings must map resolver names to date lists');
            }

            $list = [];

            foreach ($dates as $date) {
                if (! is_string($date)) {
                    throw new RuntimeException('bindings must map resolver names to date lists');
                }

                $list[] = $date;
            }

            $bindings[$name] = $list;
        }

        return $bindings;
    }

    /**
     * @param  array<mixed>  $query
     */
    private function instant(array $query, string $key): DateTimeImmutable
    {
        $value = $query[$key] ?? null;

        if (! is_string($value)) {
            throw new RuntimeException("The query carries no {$key}");
        }

        return new DateTimeImmutable($value);
    }
}
