# Using the Yarunoka PHP implementation

The DSL itself is specified in the
[spec repository](https://github.com/yarunoka-dev/spec). This document is
about the PHP implementation (`Yarunoka`) — the public classes at a
glance, the two contexts, and the typical firing-decision patterns.

**Yrnk** is the name of the DSL (short for Yarunoka), and the class
`Yrnk` is the typed tree representing one Yrnk document. Among the public
classes, the everyday faces are prefixed with Yrnk; the leaf type
representations (Schedule / Time / Vocabulary and so on) are not.

## Public classes

| Kind | Class | Role |
|---|---|---|
| behaviour | `YrnkParser` | DSL (JSON / array) → Yrnk. Validates down to the resolvability of references |
| behaviour | `Schedule\YrnkScheduleParser` | one element of schedules[] → YrnkSchedule |
| behaviour | `YrnkBuilder` / `Schedule\YrnkScheduleBuilder` | tree → DSL. Round-tripping is the identity |
| behaviour | `YrnkEvaluator` | the evaluator. A service holding configuration |
| type | `Yrnk` / `YrnkSchedule` / `YrnkDate` / `YrnkDateTime` / `Calendar\*` / `Schedule\*` / `Time\*` / `Vocabulary\*` | the typed tree isomorphic to the DSL (no evaluation methods) |
| type | `Exceptions\*` | parse, validation, and evaluation failures |

The Laravel bridge lives in the separate `yarunoka/laravel` package.

Everything under `Internal\` is implementation detail (`@internal`).
There is no backward-compatibility promise, so do not import it.

## The two contexts

**The exchange context** — bridging the DSL and objects. Yrnk is the unit
of this context and never appears in an application runtime.

```php
use Yarunoka\YrnkBuilder;
use Yarunoka\YrnkParser;

$parser = new YrnkParser(resolvers: [
    'jp-holidays' => fn (YrnkDate $from, YrnkDate $through): array => /* the holiday list for that range */,
]);

$document = $parser->parse($json);      // the typed tree; syntax + references validated
$document->timezone;                    // DateTimeZone
$document->calendar->holidays;          // ?YrnkHolidays
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
    calendar: $document->calendar,   // or a YrnkCalendar composed from the app's configuration
    timezone: $document->timezone,
    resolvers: [/* the same as the parser's */],
);

$evaluator->matches($payday, $now);                      // is this instant an occurrence?
$evaluator->hasMatchIn($payday, $lastRunAt, $now);       // is there a point after the last run, through now?
$evaluator->occurrencesIn($payday, $from, $through);     // which occurrences lie from $from through $through?
```

- The evaluation methods take a single YrnkSchedule. Questions about the
  top-level OR (the schedules list) are composed by the caller asking per
  branch (for a firing decision, the any of "fire when any has a matching
  date-time"; for an enumeration, a merge of the per-branch lists)
- The YrnkEvaluator is a service living once in the application and
  reused (bound in the DI container). **Definitions resolve per question
  and are not carried between them**, so every answer rests on what the
  resolvers say at the time it is asked. A caller that would rather not
  look the same data up again holds it inside its own resolver
- `matches` asks whether the given instant is an occurrence. For a timed
  occurrence the answer is instant equality (sub-second precision is
  truncated — no scheduled point is finer than a second); the comparison
  is between instants, never wall-clock values. An all-day occurrence
  matches on the day alone. Granularity adjustments (rounding to minutes
  and the like) are done by the caller on the value it passes
- `hasMatchIn` asks **after a, through b** — a point at the start does
  not count, a point at the end does. It looks only at the candidate
  (year, month) pairs of the period, so asking about a schedule that
  "never comes" becomes no as soon as the candidates run out
- `occurrencesIn` asks **from a through b** — the caller names two
  instants, and both are part of what it names (a caller that means to
  exclude a boundary moves it). Timed occurrences are answered
  as `YrnkDateTime` instants on the configured timezone's clock, all-day
  occurrences as `YrnkDate` dates (both are `DateTimeImmutable`
  subclasses, and the kind is read from the type) — the two kinds never
  merge — in ascending order, an all-day occurrence taking the start of
  its day as its place in the order. A window holds an all-day occurrence
  as soon as it holds any part of that day, so asking partway through a
  day still answers for it
- Scheduled points on DST transition days resolve per RFC 5545 §3.3.5 — a
  point at a nonexistent time is pushed forward, and a point at a time
  that occurs twice counts only as its first occurrence

## Composing the tree in PHP

The tree can be composed directly without the parser. The constructors
uphold the value invariants, and the YrnkEvaluator validates the
resolvability of references before evaluation.

```php
use Yarunoka\Calendar\{YrnkCalendar, YrnkCustomDefinition, YrnkHolidays};
use Yarunoka\Yrnk;
use Yarunoka\Schedule\AllDay;
use Yarunoka\YrnkSchedule;

$calendar = new YrnkCalendar(
    holidays: YrnkHolidays::byResolver('yasumi-Japan'),                  // resolved by yasumi when it is installed
    // YrnkHolidays::ofDates(['2026-01-01', ...])                        // a fixed list
    // YrnkHolidays::deferred(fn (YrnkDate $from, YrnkDate $through): array => Holiday::pluck('date')->all())  // deferred (not writable in the DSL)
    custom: ['founding-day' => YrnkCustomDefinition::ofDates(['2026-10-01'])],
);

$handmade = new Yrnk(
    version: '1.0',
    timezone: new DateTimeZone('Asia/Tokyo'),
    calendar: $calendar,
    schedules: [new YrnkSchedule(times: new AllDay, days: /* ... */)],
);
```

- A resolver or deferred closure is given **the range it should cover**
  (`resolve(YrnkDate $from, YrnkDate $through)`, both ends included) and
  returns the dates in it.
  It is asked again for a range it has not covered, so it only ever needs
  to compute what it was asked for
- Building a Yrnk containing deferred entries folds them into snapshots
  of the resolved lists (a Closure is not writable in the DSL)

## Typical firing-decision patterns (the caller)

The catch-up semantics are decided by how the caller cuts the period it
asks about.

```php
// the basic form: "was there a scheduled point since the last run?"
if ($evaluator->hasMatchIn($schedule, $lastRunAt, $now)) {
    run();
    $lastRunAt = $now;
}
```

- **Catch-up**: detecting a scheduled time after it has passed still
  fires once, late, because the point lies after the last run, through
  now. Several missed points still answer as one bool, so they collapse
  into a single firing
- **A grace cap**: just move up the start of the period
  (`$after = max($lastRunAt, $now->modify('-1 hour'))`)
- **No catch-up outside between**: detecting "hourly from 8:00 to 20:00"
  at 20:30 finds nothing, because a 20:00 scheduled point never existed
  (the half-open interval)
- **"At least N seconds apart" throttling**: an execution-side concern,
  not a schedule. AND the distance from last_run_at on the caller's side
- **allday**: a day is due for as long as it lasts, so every question
  whose window touches the day answers yes — including one asked late in
  the day. A caller that runs an all-day task once keeps that count on
  its own side; the schedule says which days, not how often to act

## Exceptions

All of them implement `Yarunoka\Exceptions\ExceptionInterface`, and each
extends the SPL exception that describes what went wrong — so catch the
interface to mean "Yarunoka failed", or the SPL type to treat it alongside
the same kind of failure from elsewhere.

| Exception | Extends | Meaning |
|---|---|---|
| `InvalidYrnkException` | `RuntimeException` | the structure or a value of the DSL violates the language (unknown key, malformed shape) |
| `UnsupportedVersionException` | `InvalidYrnkException` | a `version` this implementation does not know |
| `UndefinedNameException` | `InvalidYrnkException` | a reference to an undefined custom name |
| `ReservedNameException` | `InvalidYrnkException` | a reserved word or a literal-shaped custom name |
| `MissingCalendarDataException` | `InvalidYrnkException` | a definition required by the vocabulary is missing |
| `UnregisteredResolverException` | `RuntimeException` | a definition names a resolver the host never bound |
| `InvalidCalendarDataException` | `UnexpectedValueException` | a contract violation in a resolver or closure return value |
| `InvalidValueException` | `InvalidArgumentException` | a value format or range invariant violation on a hand-built node |

Everything the document itself got wrong is under `InvalidYrnkException`,
so a single catch covers that family.
