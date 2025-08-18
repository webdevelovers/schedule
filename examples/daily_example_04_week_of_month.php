<?php

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Schedule;
use WebDevelovers\Schedule\Enum\ScheduleInterval;

/**
 * "An event taking place daily, from 09:00 to 10:00,
 *  starting from the first of January, for three occurrences, on Mondays,
 *  but only on the first week of the month."
 *
 * Here the occurrences will be:
 * - 03-02-2025 09:00 -> 03-02-2025 10:00
 * - 03-03-2025 09:00 -> 03-03-2025 10:00
 * - 07-04-2025 09:00 -> 07-04-2025 10:00
 *
 * Because there will be only one Monday the first week of the month.
 */

$schedule = new Schedule(
    repeatInterval: ScheduleInterval::DAILY,
    startDate: new ChronosDate('2025-01-01'),
    startTime: new ChronosTime('09:00'),
    endTimeOrDuration: 'PT1H',
    repeatCount: 3,
    byDay: [DayOfWeek::MONDAY],
    byMonthWeek: [1],
);