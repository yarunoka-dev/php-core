---
title: Installation
description: Installing the package with Composer, and adding the optional holiday provider.
sidebar:
  order: 3
---

## Composer

```console
composer require yarunoka/core
```

That is the whole installation. The package registers nothing, publishes
no configuration, and has no bootstrapping step — every entry point is a
class you construct yourself.

:::caution
The 0.x releases exist to exercise the release pipeline and to track the
specification on its way to 1.0.0. They are **not intended for use**.
:::

## Resolving public holidays with yasumi

A document may name a resolver instead of listing dates:

```json
{"calendar": {"holidays": "yasumi-Japan"}}
```

A name of the form `yasumi-{Provider}` is resolved by
[yasumi](https://github.com/azuyalabs/yasumi) when that library is
installed, with no resolver of your own to write:

```console
composer require azuyalabs/yasumi
```

`{Provider}` is a yasumi provider name (`Japan`, `USA`, …). Without the
library installed, such a name is simply an unregistered resolver, and a
document using it fails validation — nothing resolves silently to an
empty list.

Any other name is yours to bind. See
[Supplying dates at runtime](usage#supplying-dates-at-runtime).

## Verifying the installation

```php
use Yarunoka\YrnkParser;

$document = (new YrnkParser())->parse('{
    "version": "1.0",
    "timezone": "Asia/Tokyo",
    "schedules": [{"days": [25], "times": ["10:00"]}]
}');

$document->timezone->getName();   // "Asia/Tokyo"
count($document->schedules);      // 1
```

## The Laravel bridge

Integration with Laravel is the job of the separate `yarunoka/laravel`
package rather than of this one. Its own documentation covers installing
and configuring it.
