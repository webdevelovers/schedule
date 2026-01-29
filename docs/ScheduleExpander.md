### Occurrence Expansion (ScheduleExpander)

Defining a schedule is only the first step.  
To **extract the actual occurrences** (dates/times when the events actually happen), you use the `ScheduleExpander`.

The expander returns a **lazy `Generator`** of `ScheduleOccurrence` objects.

#### Example: Expanding a schedule for next month only
```
<?php 

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;

$schedule = new Schedule(
    repeatFrequency: Frequency::DAILY,
    startDate: new ChronosDate('2025-06-01'),
    startTime: new ChronosTime('09:00'),
    repeatCount: 250,
    duration: 'PT1H',
    byDay: [DayOfWeek::MONDAY, DayOfWeek::WEDNESDAY],
);

$from = new DateTimeImmutable('2025-09-01');
$to = new DateTimeImmutable('2025-09-30');

//Notice how we define the boundaries of the expansion
$schedule = $schedule->withStartDate(self::chronosDate('2025-09-01'))->withEndDate(self::chronosDate('2025-09-30'));

$expander = new ScheduleExpander($schedule, new SomeHolidayProvider());

//Using the generator
foreach ($expander->expand() asoccurrence) { 
    // Process each schedule occurrence in September 2025 
}

//Using an array
$occurrences = iterator_to_array($expander->expand());
[..]
```
#### Notes

- The expander respects all the rules set in the `Schedule` object (days, months, exceptions, excluded holidays, etc.). To avoid
  infinite event generation, a maximum number of occurrences can be set with the "repeatCount" argument, or a version of the Schedule with boundaries
  can be provided (the withStartDate() and withEndDate() methods will return a new Schedule object with the boundaries set).
- To handle holidays, implement or use a compatible `HolidayProviderInterface`.
- The expander will return a generator of `ScheduleOccurrence` objects, each one representing a single occurrenct of the provided Schedule.
  If an HolidayProviderInterface is implemented and passed to the expander, the expander will also take into account holidays and inside the `ScheduleOccurrence`
  the boolean field "isHoliday" will be set to true/false depending on the provider implementation.

---

## How date windows work (`from` / `to`)

`from` and `to` are optional expansion boundaries. They are combined with the schedule’s own
`startDate`/`endDate`:

- effective start = `max(schedule.startDate, from)` (when both are set)
- effective end   = `min(schedule.endDate, to)` (when both are set)
- boundaries are **inclusive** (a schedule ending on `2025-01-03` includes `2025-01-03`)

Important behaviors:

- If both `schedule.startDate` and `from` are `null`, expansion returns no occurrences
  (the expander has no anchor to start from).
- If `schedule.startDate` is `null` but `from` is provided, the expander starts at `from`.
- If `schedule.endDate` is `null` but `to` is provided, the expander stops at `to`.

---

## Recurring vs non-recurring schedules

### Non-recurring (`repeatInterval = NONE`)

- Generates **0 or 1** occurrence.
- Overnight events are supported: if `endTime` is before `startTime`, the end datetime is moved to the next day.
- Current behavior note: `exceptDates` are currently ignored for non-recurring schedules.

### Recurring (any interval except `NONE`)

For recurring schedules, the expander currently requires enough data to compute an interval:

- If both `endTime` and `duration` are missing, no occurrences are produced.

---

## Filters, exclusions, inclusions

For each candidate date, the expander applies:

- `byDay` (weekday filter)
- `byMonth` (month filter)
- `byMonthDay` (day-of-month filter)
- `byMonthWeek` (week-of-month filter)
- `exceptDates` (explicit exclusions)
- `includeDates` (explicit inclusions)

### `includeDates` bypasses filters

If a date is in `includeDates`, it is treated as explicitly included and it bypasses other filters
(e.g. it can pass even if it’s not in `byDay`).

### Include vs exclude precedence (same day)

If the same day is both included and excluded, inclusion wins: the date will be kept.

---

## Holiday provider integration

If you pass a `HolidayProviderInterface`, each occurrence will have the `isHoliday` flag set based on
the provider’s `isHoliday(ChronosDate $date): bool` result.

If no provider is passed, `isHoliday` will be `false`.

---

## Post-filtering with `$filter`

You can pass a callable filter:

---

## Working with ScheduleAggregate

### Expand (no sorting)

`expandAggregate()` expands schedules in aggregate order and yields occurrences sequentially.

### Expand sorted (global ordering)

`expandAggregateSorted()` expands all schedules and yields occurrences ordered by start datetime.

Features:

- global sort order (ascending by default)
- lazy generator (does not materialize everything in memory)
- optional de-duplication (`unique = true`) considers two occurrences equal if they have the same start and end datetimes.

---
