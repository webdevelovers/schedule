## Schedule

`Schedule` is the core domain object of this library.

It represents an **abstract recurrence pattern** that can be used to describe
recurring or non‑recurring events such as classes, shifts, appointments or
maintenance windows. It does **not** generate occurrences by itself: expansion
is delegated to `ScheduleExpander`.

A `Schedule` is:

- validated on construction (invalid combinations throw `ScheduleException`)
- immutable (helper methods like `withStartDate()` return a new instance instead of modifying the existing one
- serializable to JSON (via `toArray()`/`toJson()`)

A `Schedule` can be configured with:

- a repeat interval (daily, weekly, monthly, yearly, …)
- an optional start/end window
- optional filters (days of week, month days, months, weeks of month)
- optional exceptions (dates to be skipped)
- optional inclusions (explicit dates to add as extra occurrences)

It is inspired by concepts from [RFC 5545](https://www.rfc-editor.org/rfc/rfc5545)
(iCalendar) and [schema.org/Schedule](https://schema.org/Schedule), but kept deliberately simpler and PHP‑friendly.

### Relation to ScheduleExpander

`Schedule` defines the **rules** (interval, filters, exceptions, bounds).  
To turn these rules into actual occurrences, you use `ScheduleExpander`:

- `ScheduleExpander::expand($schedule, $holidayProvider = null, ?ChronosDate $from = null, ?ChronosDate $to = null, ?callable $filter = null)`
- or via `ScheduleAggregate` with `expandAggregate()` / `expandAggregateSorted()`.

The expander:

- uses `repeatInterval` and `startDate`/`endDate` together with the `from`/`to`
  window to decide which dates to consider,
- applies `byDay`, `byMonth`, `byMonthDay`, `byMonthWeek`, `exceptDates`, and `includeDates`
  as filters/inclusions,
- builds `ScheduleOccurrence` instances with computed start/end datetimes and
  holiday flags.

### Field semantics

- **`repeatInterval`** (`ScheduleInterval` – required)  
  How often the schedule advances its base date, as a **fixed interval**.

  Each enum value maps directly to a fixed PHP `DateInterval`:

    - `DAILY`              → every 1 day (`P1D`)
    - `EVERY_WEEK`         → every 7 days (`P1W`)
    - `EVERY_TWO_WEEKS`    → every 14 days (`P2W`)
    - `EVERY_THREE_WEEKS`  → every 21 days (`P3W`)
    - `EVERY_FOUR_WEEKS`   → every 28 days (`P4W`)
    - `EVERY_MONTH`        → every 1 calendar month (`P1M`)
    - `EVERY_TWO_MONTHS`   → every 2 calendar months (`P2M`)
    - `EVERY_THREE_MONTHS` → every 3 calendar months (`P3M`)
    - `EVERY_FOUR_MONTHS`  → every 4 calendar months (`P4M`)
    - `EVERY_SIX_MONTHS`   → every 6 calendar months (`P6M`)
    - `EVERY_YEAR`         → every 1 calendar year (`P1Y`)
    - `NONE`               → non‑recurring (single occurrence if other fields are valid)

  > Important: `EVERY_WEEK` does **not** mean “every Tuesday” or “every Wednesday”.
  > It means: starting from **the initial date, occurrences are spaced by a fixed interval
  > of 7 days. To express “every Tuesday” (or “every Monday and Wednesday”), you use:
  >
  > - `repeatInterval: ScheduleInterval::DAILY`, **plus**
  > - `byDay: [DayOfWeek::TUESDAY]` (or multiple `DayOfWeek` values).
  > - **a tuesday as an initial date** - otherwise no occurrences will be generated.`

  Internally, `ScheduleInterval::toISO8601()` returns the ISO‑8601 string for the
  fixed interval, which is then used to build the underlying `DateInterval`.
  Convenience:
    - `ScheduleInterval::toISO8601()` returns the corresponding ISO‑8601 duration string
      (e.g. `DAILY` → `"P1D"`) used internally by the expander.
    - `ScheduleInterval::label()` returns a translation key (e.g. `"schedule.frequency.daily"`)
      suitable for use with a translation system.

- **`startDate`** (`ChronosDate|null`)  
  First calendar day when occurrences may appear (inclusive).  
  If `null`, the schedule has no intrinsic lower bound; the expander will still require
  a `from` date (window) to produce occurrences.

- **`endDate`** (`ChronosDate|null`)  
  Last calendar day when occurrences may appear (inclusive).  
  If `null`, the schedule has no intrinsic upper bound; the expander can still be bounded
  by a `to` date.

- **`startTime`** (`ChronosTime|null`)  
  Local time (in `timezone`) when each occurrence starts.  
  If `null`, occurrences start at `00:00:00` by default.

- **`endTimeOrDuration`** (`ChronosTime|DateInterval|string|null`)  
  One of:
    - `ChronosTime` – interpreted as “end time of the occurrence” (on the same day or the next day if it is before `startTime`, for overnight events),
    - `DateInterval` – interpreted as the duration; end time is computed as `startTime + duration`,
    - `string` – ISO‑8601 interval (e.g. `"PT1H30M"`), parsed into a `DateInterval`,
    - `null` – no explicit end; duration is `null` and expander will treat start and end as the same instant.

  A duration of zero (all components 0) is considered invalid and throws a `ScheduleException`.

- **`repeatCount`** (`int|null`)  
  Maximum number of occurrences to generate.  
  Must be `> 0` if provided. If `null`, the schedule is unbounded in terms of count; the
  expansion window (`from`/`to`) and/or `endDate` will control the actual output.

- **`byDay`** (`DayOfWeek[]`)  
  Optional filter by days of the week. Only occurrences that fall on one of these
  weekdays are kept. Values must be instances of the `DayOfWeek` enum, for example:
  `DayOfWeek::MONDAY`, `DayOfWeek::WEDNESDAY`. Duplicates are not allowed.

- **`byMonthDay`** (`int[]`)  
  Optional filter by days of the month. Each element must be an integer in the range
  `1..31` or `-31..-1`.  
  Positive values count from the start of the month (1 = first day), negative values
  count from the end (‑1 = last day).  
  The current expander only supports positive values; negative values are validated but
  not yet interpreted (they will effectively exclude all occurrences).

- **`byMonth`** (`Month[]`)  
  Optional filter by months of the year. Values must be instances of the `Month` enum
  (e.g. `Month::JANUARY`). Duplicates are not allowed.

- **`byMonthWeek`** (`int[]`)  
  Optional filter by week index within the month.  
  Allowed values are integers in `1..6` or `-6..-1`:
    - positive: 1 = first week, 2 = second week, …
    - negative: ‑1 = last week, ‑2 = penultimate week, etc.  
      Zero is not allowed.

- **`exceptDates`** (`ChronosDate[]`)  
  List of dates to exclude from the schedule.  
  When both `startDate` and `endDate` are set, each `exceptDates` element must fall
  within `[startDate, endDate]` (inclusive), otherwise a `ScheduleException` is thrown.
  When only one of `startDate` or `endDate` is set, the range check is skipped.

- **`includeDates`** (`ChronosDate[]`)  
  List of explicitly included dates (extra occurrences).  
  This can be used to add “one-off” dates on top of the normal recurrence rules.  
  Validation rules:
  - each element must be a `ChronosDate`
  - duplicates are not allowed
  - when both `startDate` and `endDate` are set, each included date must fall within
    `[startDate, endDate]` (inclusive)

  > Note: if the same date is both included and excluded (present in `includeDates`
  > and `exceptDates`), the effective outcome depends on the expander’s precedence rules.
  > If you need deterministic behavior, avoid overlapping entries.

- **`timezone`** (`string|null`)  
  IANA timezone name used for all occurrences (e.g. `"UTC"`, `"Europe/Rome"`).  
  If `null`, defaults to a predefined timezone (currently `"UTC"`, or `date_default_timezone_get()`
  depending on configuration). Invalid identifiers throw a `ScheduleException`.

- **`identifier`** (`string`, read‑only)  
  A deterministic hash computed from all schedule fields that affect the recurrence pattern
  (dates, times, filters, timezone, etc.).  
  Two schedules with the same logical configuration will share the same `identifier`.
  It is computed automatically and **ignored** when restoring a schedule via `fromArray()` or `fromJson()`.

---

#### Schedule Examples

The `examples/` folder contains small, focused scripts showing how to define
different kinds of schedules and how to expand them.

- **Example 1: daily event, limited by repeat count**  
  `examples/daily_example_01.php`  
  A daily schedule starting from a given date, with a fixed number of occurrences.

- **Example 2: daily event on specific weekdays**  
  `examples/daily_example_02_byDay_filter.php`  
  A daily schedule that only produces occurrences on certain days of the week
  (e.g. Mondays and Wednesdays).

- **Example 3: daily event with excluded dates**  
  `examples/daily_example_03_excluding_dates.php`  
  A schedule with one or more dates in `exceptDates`, which will be skipped during expansion.

- **Example 4: daily event on a specific week of the month**  
  `examples/daily_example_04_week_of_month.php`  
  A schedule using `byMonthWeek` (and optionally `byDay`) to express rules like
  “every last Friday of the month”.

- **Example 5: daily event in specific months and days**  
  `examples/daily_example_05_month.php`  
  A schedule restricted to certain months (`byMonth`) and month days (`byMonthDay`),
  for example “every 1st and 15th of March”.

---

## Serialization: array and JSON

```
// To array array = $schedule->toArray();
// From array restored = Schedule::fromArray(array);

// To JSON json = $schedule->toJson();
// From JSON restoredFromJson = Schedule::fromJson(json);
```

Notes:

- `fromArray()` and `fromJson()` ignore any external `identifier` and always
  recompute it from the rest of the data.
- Invalid payloads (missing or invalid `repeatInterval`, wrong types for
  filters, malformed duration strings, etc.) result in a `ScheduleException`.
- `toJson()` uses `JSON_THROW_ON_ERROR`; `fromJson()` wraps JSON errors into
  `ScheduleException`.

---