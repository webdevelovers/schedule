### ScheduleAggregate: working with multiple schedules

In many real–world scenarios you do not have a single schedule, but a **set of schedules**  
(e.g., opening hours of different branches, multiple recurring meetings, special seasonal
rules, etc.).

A `ScheduleAggregate` represents a collection of `Schedule` instances together and is:

- validated on construction (invalid elements throw `ScheduleException`);
- immutable (methods like `withAdded()`, `withSchedules()`,
  `merge()`, `sortedBy()`, `intersecting()` all return a new instance);
- serializable to/from arrays and JSON (`toArray()`, `fromArray()`, `toJson()`,
  `fromJson()`).

`ScheduleAggregate` also provides:

- a convenient container type to pass around,
- JSON / array serialization,
- tools for computing global bounds (min start / max end),
- filtering by date range,
- simple sorting utilities,
- integration with `ScheduleExpander` (`expandAggregate` / `expandAggregateSorted`).

#### Basic usage
```
php use Cake\Chronos\ChronosDate; 
use Cake\Chronos\ChronosTime; 
use WebDevelovers\Schedule\Enum\ScheduleInterval; 
use WebDevelovers\Schedule\Schedule; 
use WebDevelovers\Schedule\ScheduleAggregate;

$s1 = new Schedule;
$s2 = new Schedule;

$aggregate = new ScheduleAggregate([s1, $s2]);
$allSchedules = $aggregate->all(); // array of Schedule
```

- The constructor accepts an array of `Schedule` instances
- Every method that “changes” the content (`withAdded`, `withSchedules`, `merge`,
  `sortedBy`, `intersecting`) returns a **new `ScheduleAggregate` instance**. The original aggregate remains unchanged
- `withAdded()` returns a new aggregate with one additional schedule appended to the end
- `withSchedules()` discards all existing schedules and returns a new aggregate built from the provided list
- `merge()` concatenates the schedules from the current aggregate with the schedules
  from all the provided aggregates, returning a new instance
- `intersecting($from, $to)` returns a new aggregate containing only the schedules that overlap the given date window `[from, to]` (inclusive).
- `getBounds()` returns:
  ```php
  [$minStart, $maxEnd] = $aggregate->getBounds();
  ```
  where:
    - `$minStart` is the minimum non‑null `startDate` across all schedules,
    - `$maxEnd` is the maximum non‑null `endDate` across all schedules,
    - either value can be `null` if all schedules are open‑ended or missing that side.

- `getMinStartDate()` and `getMaxEndDate()` are small helpers built on top of
  `getBounds()`.

These bounds are also exported via `toArray()` / JSON, under the `bounds` key.
