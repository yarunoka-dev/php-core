---
title: Requirements
description: The PHP version the package needs, and why it carries no runtime dependencies.
sidebar:
  order: 2
---

## PHP

**PHP 8.4 or newer.**

## Runtime dependencies

**None.** The package requires nothing but PHP itself.

The engine is pure: it reads a document, answers questions about it, and
writes it back out. It executes no jobs, opens no connections, and
persists no state, so there is nothing for a dependency to do. Keeping
the requirement empty means the package can sit in any application
without pulling a tree of its own behind it.

## Optional

| Package | What it adds |
|---|---|
| `azuyalabs/yasumi` | Public holidays resolved automatically, without writing a resolver of your own |

The engine asks a **resolver** for the dates behind a name such as
`holidays`. You can supply one yourself — see
[Supplying dates at runtime](usage#supplying-dates-at-runtime) — or
install yasumi and let the package resolve them. It is a Composer
`suggest`, so nothing is installed until you ask for it.

## Timezone data

Interpretation follows the timezone declared in the document, resolved
against **the tz database available to your PHP installation**. Whether a
zone name exists, and where its transitions fall, is therefore a property
of the host rather than of the document. Keeping the tz data current is
part of keeping the host current.
