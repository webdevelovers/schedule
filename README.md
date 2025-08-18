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
The library uses [Chronos](https://github.com/cakephp/chronos) for dates/times inside schedule for representing dates/times as pure objects (without timezones or unused parts).

### Schedule: Model and Fields

A `Schedule` describes a recurrence pattern, similar in philosophy to standards like [RFC 5545](https://tools.ietf.org/html/rfc5545) (iCalendar) or [schema.org/Schedule](https://schema.org/Schedule).  
It can represent recurring rules (e.g., "every Monday and Thursday in June, at 10:00 AM"), as well as single (non-recurring) events.

Here's an explanation of its fields:

- **repeatInterval** (`ScheduleInterval`): How often an event repeats (e.g., `DAILY`, `EVERY_WEEK`, `EVERY_THREE_WEEKS`, `EVERY_MONTH`, etc.).
- **startDate** (`ChronosDate|null`): The start date of the schedule: occurrences begin from this date onward. 
If not specified "an unlimited number of occurrences" will span in the past, potentially.
- **endDate** (`ChronosDate|null`): The last date of the schedule: occurrences won't be generated after this date.
If not specified "an unlimited number of occurrences" will span in the future, potentially.
- **startTime** (`ChronosTime|null`): The time each occurrence starts.
- **endTimeOrDuration** (`ChronosTime|DateInterval|string|null`): A ChronosTime representing the end time of the occurrences (e.g. "10:00"), a php DateInterval for duration, or a textual ISO8601 representation of the duration (e.g. "PT1H")..
- **repeatCount** (`int|null`): Maximum number of occurrences to generate (e.g. "this event will be repeated 10 times").
- **byDay** (`DayOfWeek[]`): Filter occurrences to specific days of the week (e.g., Monday, Wednesday).
- **byMonthDay** (`int[]`): Filter occurrences to specific days of the month (e.g., "1,12,25").
- **byMonth** (`Month[]`): Filter occurrences to specific months (e.g., January, September).
- **byMonthWeek** (`int[]`): Filter occurrences to specific weeks of the month (e.g., `1` for the first week, `-1` for the last).
- **exceptDates** (`ChronosDate[]`): Exclude specific dates (with or without time).

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
infinite event generation a maximum number of occurrences can be set with the "repeatCount" argument, or a version of the Schedule with boundaries 
can be provided (the withStartDate() and withEndDate() methods will return a new Schedule object with the boundaries set).
- To handle holidays, implement or use a compatible `HolidayProviderInterface`.
- The expander will return a generator of `ScheduleOccurrence` objects, each one representing a single occurrenct of the provided Schedule. 
If an HolidayProviderInterface is implemented and passed to the expander, the expander will also take into account holidays and inside the `ScheduleOccurrence` 
the boolean field "isHoliday" will be set to true/false depending on the provider implementation.

### Humanizing a period

```
<?php

$now = new DateTimeImmutable('2025-01-01');
$schedule = new Schedule(
    repeatFrequency: Frequency::DAILY,
    startDate: new ChronosDate('2025-06-01'),
    startTime: new ChronosTime('09:00'),
);

$translator = //Implement HumanizerTranslatorInterface
$humanizer = new ScheduleHumanizer($schedule, $translator);

echo $humanizer->humanize(); //outputs a text representation of the Schedule object (e.g. "An event takes places everyday starting from 01/01/2025")
```

You can pass your custom `$translator` to the schedule humanizer.
## Extensibility

- You can provide custom translators for localization.
- You can provide your own holiday provider.

A set of interfaces are defined such as:
- HolidayProviderInterface: for managing local/country holidays.
- HumanizerTranslatorInterface: for managing localization/translation of the Humanizer processes

You can define your own implementations of these interfaces:
- HumanizerTranslatorInterface is compatible with symfony/translation translator, but in the test folder a dummy "ArrayTranslator" is provided as a reference example
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