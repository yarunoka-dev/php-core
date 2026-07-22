# yarunoka/core

Calendar-aware schedule DSL and pure occurrence query engine.

## What is Yarunoka?

Real-world schedules are calendar rules, not clock rules. "Payday is the
25th — moved up to the previous business day when that falls on a weekend
or a holiday." "Collection day is the second Tuesday of every month."
"The poller runs every 90 minutes, but only within business hours." Cron expressions and
plain timestamps cannot carry these rules, so they end up as scattered
application code — hard to store, hard to display, and impossible for
users to edit safely.

Yarunoka is a small JSON DSL — **Yrnk** — that states such rules as data,
plus an engine that answers questions about them:

- **A document** carries a timezone, a **calendar**, and a list of
  **schedules**. The calendar is the definitions that give meaning to the
  calendar vocabulary: holidays, business holidays, extra business days,
  the workweek, business hours, and custom named date sets. A date set is
  written as a fixed date list or as the name of a resolver the
  application registers at runtime.
- **A schedule** combines a day expression (days of the month, weekdays,
  ordinal weekdays, calendar words such as `holiday`, day cycles), an
  optional **shift** rule ("the previous business day"), and the times of
  day (fixed points, grids such as every 90 minutes, or all-day).
- **The engine is pure.** It executes no jobs and persists no state; it
  answers "does this date-time match?" and "was there an occurrence in
  this interval?". Firing, catch-up, and throttling remain design decisions of
  the caller.

The name is the Japanese question **やるのか？** (*yaru no ka?*) —
roughly "so, do we do it?". That is the question this engine exists to
answer.

The DSL is language-independent and specified in the
[spec repository](https://github.com/yarunoka-dev/spec). This package is
its PHP implementation.

> [!WARNING]
> The 0.x releases exist to exercise the release pipeline and to track
> the specification on its way to 1.0.0. They are **not intended for
> use**. This notice will be removed at 1.0.0.

## Installation

```console
composer require yarunoka/core
```

Requires PHP 8.4 or newer. No runtime dependencies.

## Quick example

```php
use Yarunoka\Parser\YrnkParser;
use Yarunoka\YrnkEvaluator;

$json = <<<'JSON'
{
    "version": "1.0",
    "timezone": "Asia/Tokyo",
    "calendar": {
        "holidays": ["2026-01-01", "2026-07-20"],
        "business_holidays": [],
        "business_days": []
    },
    "schedules": [
        {"days": [25], "shift": ["prev", "or_same", "business_day"], "times": ["10:00"]}
    ]
}
JSON;

$document = (new YrnkParser())->parse($json);
$payday = $document->schedules[0];

$evaluator = new YrnkEvaluator(
    calendar: $document->calendar,
    timezone: $document->timezone,
);

// 2026-07-25 is a Saturday, so the payday shifts back to Friday the 24th.
$evaluator->matches($payday, new DateTimeImmutable('2026-07-24T10:00:00+09:00')); // true
$evaluator->matches($payday, new DateTimeImmutable('2026-07-25T10:00:00+09:00')); // false

// The poller's question: was there an occurrence since the last run?
$lastRunAt = new DateTimeImmutable('2026-07-24T09:55:00+09:00');
$now = new DateTimeImmutable('2026-07-24T10:05:00+09:00');
$evaluator->hasMatchIn($payday, $lastRunAt, $now); // true
```

## Documentation

- [Using the PHP implementation](docs/php-usage.md) — the public classes,
  the two contexts, and the typical firing-decision patterns
- [The spec repository](https://github.com/yarunoka-dev/spec) — the DSL
  specification. The JSON Schemas under `schema/` are a verbatim copy of
  the spec's (the spec declares that every language implementation
  carries a copy)
- The Laravel bridge is the separate
  [yarunoka/laravel](https://github.com/yarunoka-dev/php-laravel) package

## License

[MIT](LICENSE)
