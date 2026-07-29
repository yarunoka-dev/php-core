---
title: Usage
description: Reading a document into objects, writing objects back out as a document, asking the engine about a schedule, and supplying dates at runtime.
sidebar:
  order: 4
---

The package does three things: it **reads** a Yrnk document into typed
objects, **writes** those objects back out as a document, and **answers
questions** about the occurrences a schedule denotes.

Reading and writing are exact inverses — building what you parsed gives
back what you started with — so a document can travel between storage,
an editing UI, and the engine without any step being the one that loses
information.

## Reading a document

`YrnkParser` turns a document into a `Yrnk`: the whole document as typed
objects, validated down to the resolvability of every name it refers to.

```php
use Yarunoka\YrnkParser;

$document = (new YrnkParser())->parse($json);   // a JSON string, or an already-decoded array

$document->timezone;             // DateTimeZone — every schedule is interpreted in this zone
$document->calendar;             // YrnkCalendar — the definitions
$document->schedules;            // list<YrnkSchedule> — the schedules, in document order
$payday = $document->schedules[0];
```

`parse()` accepts either a JSON string or a decoded array, so a document
that arrived as a request body, a config file, or a database column needs
no preparation beyond whatever decoded it.

### One schedule, one calendar

The same reading is available at two smaller units, for applications that
store schedules individually rather than as whole documents:

```php
use Yarunoka\Calendar\YrnkCalendarParser;
use Yarunoka\Schedule\YrnkScheduleParser;

$schedule = (new YrnkScheduleParser())->parse(
    ['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']],
    $timezone,
);

$calendar = (new YrnkCalendarParser())->parse(
    ['holidays' => ['2026-01-01'], 'workweek' => ['mon', 'tue', 'wed', 'thu', 'fri']],
    $timezone,
);
```

Both take the timezone explicitly, because a schedule or a calendar
written on its own carries no document to declare one. A calendar holds
wall-clock dates and times and defines no zone of its own; the zone is
the document's, and here you supply it in the document's place.

## Writing a document

`YrnkBuilder` is the inverse of `YrnkParser`, and there is an inverse for
each smaller unit too.

```php
use Yarunoka\YrnkBuilder;
use Yarunoka\Calendar\YrnkCalendarBuilder;
use Yarunoka\Schedule\YrnkScheduleBuilder;

(new YrnkBuilder())->build($document);              // array — the decoded document
(new YrnkBuilder())->toJson($document);             // string — the same, encoded

(new YrnkScheduleBuilder())->build($schedule);      // array — one element of schedules[]
(new YrnkCalendarBuilder())->build($calendar, $timezone);   // array — the calendar object
```

| Unit | Read | Write |
|---|---|---|
| A whole document | `YrnkParser` | `YrnkBuilder` |
| One schedule | `Schedule\YrnkScheduleParser` | `Schedule\YrnkScheduleBuilder` |
| One calendar | `Calendar\YrnkCalendarParser` | `Calendar\YrnkCalendarBuilder` |

**Round-tripping is the identity.** Parsing a document and building it
again produces the same array you started from — not an equivalent
spelling, the same one. The language has no scalar sugar and no optional
punctuation precisely so that this holds, which is what makes it safe to
let a UI parse, edit, and write back.

## Composing a document in PHP

A document does not have to start life as JSON. When the rules come from
your application rather than from a stored document, compose the parts
and hand them to `Yrnk`:

```php
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkHolidaysDateSet;
use Yarunoka\Schedule\YrnkScheduleParser;
use Yarunoka\Yrnk;

$scheduleParser = new YrnkScheduleParser();

$document = new Yrnk(
    version:   Yrnk::SUPPORTED_VERSION,
    timezone:  $timezone,
    calendar:  new YrnkCalendar(
        holidays: YrnkHolidaysDateSet::ofDates($datesFromDatabase, $timezone),
    ),
    schedules: array_map(
        static fn (array $raw) => $scheduleParser->parse($raw, $timezone),
        $rawSchedules,
    ),
);
```

Note the division of labour. **Calendar definitions are composed
directly** — they are where your application's own data goes in, and a
holiday list out of a database is not something you would write as a
literal. **Schedules are parsed from arrays**, because a schedule is an
expression of the language: writing one as a tree of objects means
writing the parser's output by hand, and the array spelling is both
shorter and the one the language defines. Building the composed document
gives you the document text, so this is also how you generate a document
to store.

`ofDates()` takes date strings or `DateTimeInterface`
values, so dates already loaded as objects can go straight in — only the
wall-clock date is read from them, never the instant, since a holiday is
a day rather than a moment.

The other definitions compose the same way:

```php
use Yarunoka\Calendar\{YrnkBusinessHours, YrnkDateSet, YrnkWorkweek};
use Yarunoka\Time\YrnkTimeWindow;
use Yarunoka\Vocabulary\YrnkDayName;

new YrnkCalendar(
    holidays:      YrnkHolidaysDateSet::ofDates($dates, $timezone),
    workweek:      new YrnkWorkweek([YrnkDayName::Mon, YrnkDayName::Tue, YrnkDayName::Wed]),
    businessHours: new YrnkBusinessHours([YrnkTimeWindow::fromStrings('09:00', '18:00')]),
    dateSets:      ['founding-day' => YrnkDateSet::ofDates(['2026-10-01'], $timezone)],
);
```

Every date list is a `YrnkDateSet`. The built-in ones are subclasses of
it — `YrnkHolidaysDateSet`, `YrnkBusinessHolidaysDateSet`,
`YrnkBusinessDaysDateSet` — so a position takes the kind its key means
and refuses another. Entries of `dateSets` are the plain kind: names of
your own, taking no part in the layer model.

## Asking the engine

`YrnkEvaluator` is a service: you build it once from the definitions and
the timezone, and hand it a schedule per question.

```php
use Yarunoka\YrnkEvaluator;

$evaluator = YrnkEvaluator::fromYrnk($document);

$evaluator->matches($payday, $now);                        // is this instant an occurrence?
$evaluator->hasMatchIn($payday, $lastRunAt, $now);         // is there a point after the last run, through now?
$evaluator->occurrencesIn($payday, $from, $through);       // which occurrences lie from $from through $through?
```

The three questions differ in what they take and what they mean at the
boundaries:

- **`matches`** asks whether the given instant is an occurrence. For a
  timed occurrence the answer is instant equality (sub-second precision
  is truncated — no scheduled point is finer than a second); the
  comparison is between instants, never wall-clock values. An all-day
  occurrence matches on the day alone
- **`hasMatchIn`** asks **after a, through b** — a point exactly at the
  start does not count, a point exactly at the end does. Each question's
  "now" becomes the next one's start, so every point is seen exactly once
  across a series of questions
- **`occurrencesIn`** asks **from a through b** — the caller names two
  instants and both are part of what it names. Timed occurrences come
  back as `YrnkDateTime`, all-day occurrences as `YrnkDate`; both extend
  `DateTimeImmutable`, and which kind you got is read from the type. The
  order is ascending, an all-day occurrence taking the start of its day
  as its place

An **all-day occurrence is held for as long as its day lasts**: any
question whose range touches the day answers for it, including one asked
late in the evening. That is not the same as a timed occurrence at 00:00,
and the two never collapse into one.

Scheduled points on DST transition days resolve per RFC 5545 §3.3.5 — a
point at a nonexistent time is pushed forward, and a point at a time that
occurs twice counts only as its first occurrence.

`fromYrnk()` takes the two things it needs from the document together.
The constructor is there for a runtime that never assembles a document —
schedules kept as rows of a table, the rest read from configuration:

```php
$evaluator = new YrnkEvaluator($calendar, $timezone);
```

Take a document apart and hand the pieces back in and you have made it
possible to read a calendar on a zone it was not written against, which
is the one thing `fromYrnk()` is for.

### One schedule at a time

Every method takes a single `YrnkSchedule`. A document's `schedules` list
is an OR, so a question about the whole document is composed by the
caller: `any` across the branches for a decision, a merge of the lists
for an enumeration. Nothing is lost by doing it that way — it just makes
the composition yours to control.

### Definitions are resolved per question

The evaluator holds no results between questions. Every answer rests on
what the resolvers say at the moment it is asked, which is what lets a
long-lived service pick up a holiday list that changed underneath it. If
you would rather not look the same data up repeatedly, hold it inside
your own resolver — see below.

## Names the host resolves

Wherever a date list is expected, a **name** may be written instead. A
name denotes a date set, and it resolves one of two ways: **inside** the
document, as an entry of `date_sets`, or **outside** it, by a binding the
host supplies — a **resolver**. Which of the two makes no difference to
where the name may be written.

A document declares the names it leaves outside itself:

```json
{
  "version": "1.0",
  "timezone": "Asia/Tokyo",
  "resolvers": ["company-holidays"],
  "calendar": {
    "holidays": "company-holidays",
    "date_sets": {"founding-day": ["2026-10-01"]}
  },
  "schedules": [
    {"days": ["holiday"], "times": ["09:00"]},
    {"days": ["founding-day"], "allday": true}
  ]
}
```

**The declaration is complete**: every name that is used and not defined
has to be listed, so what the document says is exactly what you have to
bind. That is what lets a host prepare bindings from the document alone,
before parsing it — which otherwise could not be done, since parsing is
what needs them.

A declared name that goes unused is fine. A name cannot be both a
`date_sets` entry and a declared one.

## Supplying dates at runtime

Bind each declared name to something that produces dates, and hand the
container to the parser:

```php
use Yarunoka\Resolvers\YrnkResolverContainer;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkParser;

$resolvers = new YrnkResolverContainer();
$resolvers->add('company-holidays', new CompanyHolidays($repository));

$document  = (new YrnkParser($resolvers))->parse($json);
$evaluator = YrnkEvaluator::fromYrnk($document);
```

**The bindings are handed over once.** They ride on the calendar the
parser builds, so whoever holds the calendar can answer from it and there
is no second place to pass them and forget. Parsing a document whose
declared names are not all bound raises `UnregisteredResolverException`
naming **all** of the missing ones, not the first.

A resolver is handed **the range it has to cover**, both ends included,
and returns `YYYY-MM-DD` strings. Dates outside the range are ignored,
and dates missing inside it read as "not in this set". It is asked again
whenever a range it has not covered is reached, so it only ever needs to
compute what it was asked for.

### Writing one

A resolver implements a contract, so anything with a constructor — a
repository, a cache, an HTTP client — can be one, and a DI container can
bind them by type:

```php
use Yarunoka\Resolvers\YrnkHolidaysResolverInterface;
use Yarunoka\YrnkDate;

final class CompanyHolidays implements YrnkHolidaysResolverInterface
{
    public function __construct(private readonly HolidayRepository $repository) {}

    public function resolve(YrnkDate $from, YrnkDate $through): array
    {
        return $this->repository->between($from, $through);
    }
}
```

`YrnkResolverInterface` is the base contract;
`YrnkHolidaysResolverInterface`, `YrnkBusinessHolidaysResolverInterface`,
and `YrnkBusinessDaysResolverInterface` mark which layer a resolver
supplies. For a one-off, an anonymous class implementing the interface
does the job:

```php
$resolvers->add('company-holidays', new class implements YrnkHolidaysResolverInterface {
    public function resolve(YrnkDate $from, YrnkDate $through): array
    {
        return ['2026-01-01'];
    }
});
```

Holding results across calls is the implementation's own decision — the
engine resolves per question and keeps nothing between them.

### Public holidays without writing one

A name spelled `yasumi-{Provider}` is bound already when
[yasumi](https://github.com/azuyalabs/yasumi) is installed — declare it
like any other name and bind nothing:

```json
{"resolvers": ["yasumi-Japan"], "calendar": {"holidays": "yasumi-Japan"}}
```

These are ordinary bindings seeded before yours, so adding one of your
own under the same name is a duplicate and raises rather than quietly
overriding.

## Handling failures

Everything this package throws implements
`Yarunoka\Exceptions\ExceptionInterface`, and each exception also extends
the SPL class that describes what kind of failure it is. Catch whichever
is the right grain:

```php
use Yarunoka\Exceptions\ExceptionInterface;
use Yarunoka\Exceptions\InvalidYrnkException;

try {
    $document = (new YrnkParser())->parse($json);
} catch (InvalidYrnkException $e) {
    // The document itself is wrong — show the author what to fix
} catch (ExceptionInterface $e) {
    // Anything else Yarunoka refused
}
```

`InvalidYrnkException` is the family covering everything the document got
wrong, so a single catch handles "this document is not valid" without
enumerating the cases. Catching the SPL type instead
(`\RuntimeException`, `\InvalidArgumentException`, …) treats a Yarunoka
failure alongside the same kind of failure from anywhere else.

The reference lists every exception with its SPL parent and what raises
it.

## Deciding when to fire

The engine answers questions; it does not run anything. Catch-up, grace,
and throttling are decided by how the caller cuts the period it asks
about.

```php
if ($evaluator->hasMatchIn($schedule, $lastRunAt, $now)) {
    run();
    $lastRunAt = $now;
}
```

- **Catch-up** — noticing a scheduled time after it has passed still
  fires, late, because the point lies after the last run and through now.
  Several missed points answer as one `true`, so they collapse into a
  single firing rather than a burst
- **A grace cap** — move up the start of the period:
  `max($lastRunAt, $now->modify('-1 hour'))`. Anything older than the cap
  is not caught up
- **No catch-up where no point existed** — asking at 20:30 about "hourly
  from 8:00 until 20:00" finds nothing, because a 20:00 point never
  existed. The window is half-open, and the schedule is the authority on
  what exists
- **"At least N seconds apart"** — throttling, not scheduling. AND the
  distance from the last run on your side; the language deliberately has
  no way to say it
- **All-day tasks** — a day is due for as long as it lasts, so every
  question touching that day answers yes. A caller that means to run once
  keeps that count itself; the schedule says which days, not how often to
  act
