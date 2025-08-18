<?php

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Schedule;
use WebDevelovers\Schedule\Enum\ScheduleInterval;

/**
 * "An event taking place daily, from 09:00 to 10:00,
 *  starting from the first of January, for three occurrences, on Mondays,
 *  but only on the first week of the month, and only in January, March, and June."
 *
 * Here the occurrences will be:
 * - 01-06-2026 09:00 -> 01-06-2026 10:00
 * - 01-03-2027 09:00 -> 01-03-2027 10:00
 * - 01-01-2029 09:00 -> 01-01-2029 10:00
 *
 * Because there's no monday the first week of January 2025,
 * so we overflow into 2026
 */

$schedule = new Schedule(
    repeatInterval: ScheduleInterval::DAILY,
    startDate: new ChronosDate('2025-01-01'),
    startTime: new ChronosTime('09:00'),
    endTimeOrDuration: 'PT1H',
    repeatCount: 3,
    byDay: [DayOfWeek::MONDAY],
    byMonth: [Month::JANUARY, Month::MARCH, Month::JUNE],
    byMonthWeek: [1],
);