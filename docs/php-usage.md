# Using the Yarunoka PHP implementation

The DSL itself is specified in the
[spec repository](https://github.com/yarunoka-dev/spec). This document is
about the PHP implementation (`Yarunoka`) — the public classes at a
glance, the two contexts, and the typical firing-decision patterns.

**Yrnk** is the name of the DSL (short for Yarunoka), and the class
`Yrnk` is the typed tree representing one Yrnk document. Among the public
classes, the everyday faces are prefixed with Yrnk; the leaf type
representations (Expression / Time / Vocabulary and so on) are not.

## Public classes

| Kind | Class | Role |
|---|---|---|
| behaviour | `Parser\YrnkParser` | DSL (JSON / array) → Yrnk. Validates down to the resolvability of references |
| behaviour | `Parser\ScheduleParser` | one element of schedules[] → YrnkSchedule |
| behaviour | `Builder\YrnkBuilder` / `Builder\ScheduleBuilder` | tree → DSL. Round-tripping is the identity |
| behaviour | `YrnkEvaluator` | the evaluator. A service holding configuration |
| type | `Yrnk` / `YrnkSchedule` / `Definitions\*` / `Expression\*` / `Time\*` / `Vocabulary\*` | the typed tree isomorphic to the DSL (no evaluation methods) |
| type | `Exceptions\*` | parse, validation, and evaluation failures |

The Laravel bridge lives in the separate `yarunoka/laravel` package.

Everything under `Internal\` is implementation detail (`@internal`).
There is no backward-compatibility promise, so do not import it.

## The two contexts

**The exchange context** — bridging the DSL and objects. Yrnk is the unit
of this context and never appears in an application runtime.

```php
use Yarunoka\Builder\YrnkBuilder;
use Yarunoka\Parser\YrnkParser;

$parser = new YrnkParser(resolvers: [
    'yasumi-jp' => fn (): array => /* compute the holiday list with yasumi or the like */,
]);

$document = $parser->parse($json);      // the typed tree; syntax + references validated
$document->timezone;                    // DateTimeZone
$document->definitions->holidays;       // ?Holidays
$payday = $document->schedules[0];      // YrnkSchedule

(new YrnkBuilder)->toJson($document);   // back to the same array representation as the original JSON (the identity)
```

**The execution context** — the application's everyday. First there is
configuration (the definitions and the timezone), and a service instance
(YrnkEvaluator) composed from it. A routine holds a YrnkSchedule and
hands it to the service for evaluation.

```php
use Yarunoka\YrnkEvaluator;

$evaluator = new YrnkEvaluator(
    definitions: $document->definitions,   // or a Definitions composed from the app's configuration
    timezone: $document->timezone,
    resolvers: [/* the same as the parser's */],
);

$evaluator->matches($payday, $now);                 // does this date-time match?
$evaluator->hasMatchIn($payday, $lastRunAt, $now);  // was there one since the last run?
```

- The evaluation methods take a single YrnkSchedule. Questions about the
  top-level OR (the schedules list) are composed by the caller asking per
  branch (for a firing decision, the any of "fire when any has a matching
  date-time")
- The YrnkEvaluator is a service living once in the application and
  reused (bound in the DI container). It memoizes resolver results inside
  the instance, and **a resolver is called at most once in the lifetime
  of the instance**. Freshness rides on the binding scope (in a resident
  process, request scope keeps it naturally fresh)
- Beyond the day decision, `matches` lets the schedule decide whether the
  time takes part — with times, the value must equal one of the points
  expanded on the configured timezone's wall clock (to the second;
  sub-second precision is truncated); with allday, only the day is
  checked. Granularity adjustments (rounding to minutes and the like) are
  done by the caller on the value it passes
- `hasMatchIn` is the half-open interval **(from, to]** — a point at from
  does not count, a point at to counts. It looks only at the candidate
  (year, month) pairs of the interval, so asking about a schedule that
  "never comes" becomes no as soon as the candidates run out
- Scheduled points on DST transition days resolve per RFC 5545 §3.3.5 — a
  point at a nonexistent time is pushed forward, and a point at a time
  that occurs twice counts only as its first occurrence

## Composing the tree in PHP

The tree can be composed directly without the parser. The constructors
uphold the value invariants, and the YrnkEvaluator validates the
resolvability of references before evaluation.

```php
use Yarunoka\Definitions\{CustomDefinition, Definitions, Holidays};
use Yarunoka\Yrnk;
use Yarunoka\Expression\AllDay;
use Yarunoka\YrnkSchedule;

$definitions = new Definitions(
    holidays: Holidays::byResolver('yasumi-jp'),                     // a resolver name reference
    // Holidays::ofDates(['2026-01-01', ...])                        // a fixed list
    // Holidays::deferred(fn (): array => Holiday::pluck('date')->all())  // deferred (not writable in the DSL)
    custom: ['founding-day' => CustomDefinition::ofDates(['2026-10-01'])],
);

$handmade = new Yrnk(
    version: 1,
    timezone: new DateTimeZone('Asia/Tokyo'),
    definitions: $definitions,
    schedules: [new YrnkSchedule(times: new AllDay, days: /* ... */)],
);
```

- A resolver or deferred closure takes **no arguments and returns dates
  covering the range being evaluated** (which years to return is decided
  entirely by the side writing it)
- Building a Yrnk containing deferred entries folds them into snapshots
  of the resolved lists (a Closure is not writable in the DSL)

## Typical firing-decision patterns (the caller)

The catch-up semantics are decided by how the caller cuts the question
interval.

```php
// the basic form: "was there a scheduled point since the last run?"
if ($evaluator->hasMatchIn($schedule, $lastRunAt, $now)) {
    run();
    $lastRunAt = $now;
}
```

- **Catch-up**: detecting a scheduled time after it has passed still
  fires once, late, because the point is in (last_run_at, now]. Several
  missed points still answer as one bool, so they collapse into a single
  firing
- **A grace cap**: just trim the lower bound of the question interval
  (`$from = max($lastRunAt, $now->modify('-1 hour'))`)
- **No catch-up outside between**: detecting "hourly from 8:00 to 20:00"
  at 20:30 finds nothing, because a 20:00 scheduled point never existed
  (the half-open interval)
- **"At least N seconds apart" throttling**: an execution-side concern,
  not a schedule. AND the distance from last_run_at on the caller's side
- **allday**: the scheduled point is the start of the day, 00:00, so it
  is picked up exactly once by the first question after the date changes.
  "Any time within the day" is achieved by not trimming the grace

## Exceptions

All of them are subclasses of `Yarunoka\Exceptions\YarunokaException`.

| Exception | Meaning |
|---|---|
| `InvalidYrnkException` | the structure or a value of the DSL violates the language (unknown key, malformed shape) |
| `UnsupportedVersionException` | a `version` this implementation does not know |
| `UndefinedNameException` | a reference to an undefined custom name or an unregistered resolver name |
| `ReservedNameException` | a reserved word or a literal-shaped custom name |
| `MissingCalendarDataException` | a definition required by the vocabulary is missing |
| `InvalidCalendarDataException` | a contract violation in a resolver or closure return value |
| `InvalidValueException` | a value format or range invariant violation |
