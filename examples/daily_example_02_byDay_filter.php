<?php

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
 * But the first of January will be included if holidays are ignored.
 * Notice how the Schedule itself has no endDate, so it's limited only
 * by the maximum number of occurrences specified.
 */

$schedule = new Schedule(
    repeatInterval: ScheduleInterval::DAILY,
    startDate: new \DateTime('2025-01-01', new \DateTimeZone('UTC')),
    startTime: new \DateTime('2025-01-01 09:00'),
    repeatCount: 5,
    duration: 'PT1H',
    byDay: [DayOfWeek::MONDAY, DayOfWeek::WEDNESDAY],
);
