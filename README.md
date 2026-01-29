## Installation
```
composer require webdevelovers/schedule
```

## Overview

A lightweight PHP library for working with recurring schedules and their occurrences.  
This package is framework‑agnostic and designed with extensibility in mind, featuring:

- Framework‑agnostic
- A rich `Schedule` model for defining recurring (and one‑off) events
- Expansion utilities (`ScheduleExpander`) for turning schedules into concrete occurrences
- Human‑readable schedule representation (`ScheduleHumanizer`)
- Focused on immutability and clear, testable domain logic
- Pluggable holiday providers

The library uses [Chronos](https://github.com/cakephp/chronos) for dates and times
(`ChronosDate` and `ChronosTime`), in order to work with immutable, time‑zone‑aware
objects and avoid global state.

The library is heavilty inspired by [Recurrence Rules](https://tools.ietf.org/html/rfc5545#section-3.3.10)
and [Schema.org](https://schema.org/Schedule)
---

## Quick Start

Define a simple daily schedule and expand it into occurrences:

---

## Documentation

The documentation is split into focused documents:

- **Schedule** – model, fields, validation rules, serialization, and examples  
  👉 [`docs/Schedule.md`](docs/Schedule.md)

- **ScheduleAggregate** – working with multiple schedules, bounds, merging, sorting, intersection  
  👉 [`docs/ScheduleAggregate.md`](docs/ScheduleAggregate.md)

- **ScheduleExpander** – turning schedules and aggregates into concrete occurrences,  
  windows (`from`/`to`), filters, holiday providers, sorted expansion  
  👉 [`docs/ScheduleExpander.md`](docs/ScheduleExpander.md)

- **ScheduleHumanizer** – creating a human-readable representation of a schedule 
  👉 [`docs/ScheduleHumanizer.md`](docs/ScheduleHumanizer.md)

- **ScheduleMetrics** – Retrieve useful information about a schedule
  👉 [`docs/ScheduleMetrics.md`](docs/ScheduleMetrics.md)
---

## Extensibility

The library is designed to be extensible:

- **HolidayProviderInterface** – plug in your own holiday provider.
- **HumanizerTranslatorInterface** – integrate with your translation system to
  produce localized, human‑readable descriptions of schedules.

See the tests and the `docs/` folder for reference implementations and usage patterns.

## License

This package is open-source software licensed under the [MIT license](LICENSE).