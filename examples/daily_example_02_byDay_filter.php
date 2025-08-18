<?php

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Schedule;
use WebDevelovers\Schedule\Enum\ScheduleInterval;

/**
 * "An event taking place daily (on Mondays and Wednesday),
 *  from 09:00 to 10:00 (1h duration) starting from the first of January,
 *  for five occurrences."
 *
 * In this case the occurrences will be:
 * - 08-01-2025 09:00 -> 08-01-2025 10:00
 * - 13-01-2025 09:00 -> 13-01-2025 10:00
 * - 15-01-2025 09:00 -> 15-01-2025 10:00
 * - 20-01-2025 09:00 -> 20-01-2025 10:00
 * - 22-01-2025 09:00 -> 22-01-2025 10:00
 *
 * The first of January will be included if holidays are ignored.
 * Notice how the Schedule itself has no endDate, so it's limited only
 * by the maximum number of occurrences specified.
 */

$schedule = new Schedule(
    repeatInterval: ScheduleInterval::DAILY,
    startDate: new ChronosDate('2025-01-01'),
    startTime: new ChronosTime('09:00'),
    endTimeOrDuration: 'PT1H',
    repeatCount: 5,
    byDay: [DayOfWeek::MONDAY, DayOfWeek::WEDNESDAY],
);
