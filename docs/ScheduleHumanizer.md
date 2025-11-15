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
