---
title: yarunoka/core
description: The PHP implementation of the Yrnk schedule DSL — reading and writing documents, and asking an engine about them.
sidebar:
  order: 1
---

`yarunoka/core` is the PHP implementation of **Yrnk**, the JSON DSL for
calendar-aware schedules. It parses a document into typed objects, writes
those objects back out as a document, and answers questions about the
occurrences a schedule denotes.

The language itself — what a document may say and what it means — is
defined in the [spec repository](https://github.com/yarunoka-dev/spec/tree/1.1).
This documentation is about the PHP package only.

- **Guides** — what the package needs, how to install it, and how to use
  it
- **Reference** — the public classes and their members, generated from
  the source

The Laravel bridge lives in the separate `yarunoka/laravel` package.
