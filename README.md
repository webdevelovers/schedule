## Installation
```
composer require webdevelovers/schedule
```

## Overview

A lightweight PHP library for working with recurring events.  
This package is framework-agnostic and designed with extensibility in mind, featuring:

- Scheduling utilities (schedule definition, occurrence calculation, and browsing)
- Human-readable schedule representation
- Pluggable holiday providers support

You can use this library to define a Schedule (representing a set of repeating event occurrences). <br />
The library itself provides utilities such as a ScheduleExpander and a ScheduleHumanizer that respectively will help in browsing the occurrences that a Schedule defines
and in providing a textual representation of the Schedule itself for better readability/understanding of its rules.<br />

### Schedule: Model and Fields

A `Schedule` describes a recurrence pattern, similar in philosophy to standards like [RFC 5545](https://tools.ietf.org/html/rfc5545) (iCalendar) or [schema.org/Schedule](https://schema.org/Schedule).  
It can represent recurring rules (e.g., "every Monday and Thursday in June, at 10:00 AM"), as well as single (one-off) events.

Here's an explanation of some of some of its fields:

- **repeatFrequency** (`Frequency`): How often an event repeats (e.g., `DAILY`, `WEEKLY`, `MONTHLY`, etc.).
- **startDate** (`DateTimeInterface|null`): The start date of the schedule: occurrences begin from this date onward. 
If not specified "an unlimited number of occurrences" will span in the past, potentially.
- **endDate** (`DateTimeInterface|null`): The last date of the schedule: occurrences won't be generated after this date.
If not specified "an unlimited number of occurrences" will span in the future, potentially.
- **startTime** (`DateTimeInterface|null`): The time each occurrence starts.
- **endTime** (`DateTimeInterface|null`): The time each occurrence ends (mutually consistent with duration).
- **repeatCount** (`int|null`): Maximum number of occurrences to generate (e.g. "this event will be repeated 10 times").
- **byDay** (`DayOfWeek[]`): Filter occurrences to only some days of the week (e.g., Monday, Wednesday).
- **byMonthDay** (`int[]`): Filter occurrences to specific days of the month (e.g., 1st, 15th).
- **byMonth** (`Month[]`): Filter occurrences to specific months (e.g., January, September).
- **byMonthWeek** (`int[]`): Filter occurrences to certain weeks of the month (e.g., `1` for first week, `-1` for last).
- **exceptDates** (`DateTimeInterface[]`): Exclude specific dates (with or without time).
- **excludeHolidays** (`bool`): Exclude public holidays (according to your injected provider).

#### Schedule Examples

- [Example 1: daily event, for a maximum of three occurrences](examples/daily_example_01.php)
- [Example 2: daily event, for a maximum of five occurrences, on Mondays and Wednesdays](examples/daily_example_02_byDay_filter.php)
- [Example 3: daily event, excluding some dates](examples/daily_example_03_excluding_dates.php)
- [Example 4: daily event, on specific week of the month, and day](examples/daily_example_04_week_of_month.php)
- [Example 5: daily event, on specific month and day](examples/daily_example_05_month.php)

### Occurrence Expansion (ScheduleExpander)

Defining a schedule is only the first step.  
To **extract the actual occurrences** (dates/times when the events actually happen), you use the `ScheduleExpander`.

#### Example: Expanding a schedule for next month only
```
<?php 

$schedule = new Schedule(
    repeatFrequency: Frequency::DAILY,
    startDate: new \DateTime('2025-06-01', new \DateTimeZone('UTC')),
    startTime: new \DateTime('2025-06-01 09:00', new \DateTimeZone('UTC')),
    repeatCount: 250,
    duration: 'PT1H',
    byDay: [DayOfWeek::MONDAY, DayOfWeek::WEDNESDAY],
);

$from = new DateTimeImmutable('2025-09-01');
$to = new DateTimeImmutable('2025-09-30');

$expander = new ScheduleExpander($schedule, new SomeHolidayProvider());
foreach ($expander->expand(from, to) asoccurrence) { 
    // Process each schedule occurrence in September 2025 
}
```
#### Notes

- The expander respects all rules set in the `Schedule` object (days, months, exceptions, excluded holidays, etc.), but 
defines boundaries (from, to) and/or a "maxOccurrences" argument can be provided to avoid infinite occurrence generation.
- To handle holidays, implement or use a compatible `HolidayProviderInterface`.

### Humanizing a period

```
<?php

$now = new DateTimeImmutable('2025-01-01');
$schedule = new Schedule(
    repeatFrequency: Frequency::DAILY,
    startDate: $now,
    startTime: $now,
);

$translator = //Implement HumanizerTranslatorInterface
$humanizer = new ScheduleHumanizer($schedule, $translator);

echo $humanizer->humanize(); //outputs a text representation of the Schedule object (e.g. "An event takes places everyday starting from 01/01/2025")
```

You can pass your custom `$translator` to the schedule humanizer.
## Extensibility

- You can provide custom translators for localization.
- You can inject alternative holiday providers for your needs.

A set of interfaces are defined such as:
- HolidayProviderInterface: for managing local/country holidays that have to be excluded from occurrence generation
- HumanizerTranslatorInterface: for managing localization/translation of the Humanizer processes

You can define your own implementations of these interfaces:
- HolidayProviderInterface is compatible with symfony/translation translator, but in the test folder a dummy "ArrayTranslator" is provided as a reference example
- an example HolidayProviderInterface implementation is hereby given using an external library

#### Sample Holiday Provider (using [Yasumi](https://yasumi.dev))

Here's an example implementation for HolidayProviderInterface using Yasumi.
```
readonly class DefaultHolidayProvider implements HolidayProviderInterface
{
    public function __construct(
        private string $country, // The country name. @see https://www.yasumi.dev/providers/providers.html
    ) {
    }

    /** @throws ScheduleHumanizerException */
    public function isHoliday(DateTimeInterface $date): bool
    {
        try {
            $yasumi = Yasumi::create($this->country, (int) $date->format('Y'));
        } catch (RuntimeException | InvalidYearException | UnknownLocaleException | ProviderNotFoundException $e) {
            throw new ScheduleHumanizerException($e->getMessage(), $e->getCode(), $e);
        }

        return $yasumi->isHoliday($date);
    }
}
```

## License

This package is open-source software licensed under the [MIT license](LICENSE).