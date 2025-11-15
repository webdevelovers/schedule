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
