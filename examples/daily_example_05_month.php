<?php

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
 * - 03-03-2025 09:00 -> 03-03-2025 10:00
 * - 05-01-2026 09:00 -> 05-01-2026 10:00
 * - 02-03-2026 09:00 -> 02-03-2026 10:00
 *
 * Because there's no monday the first week of January or June 2025,
 * so we overflow into 2026
 */

$schedule = new Schedule(
    repeatInterval: ScheduleInterval::DAILY,
    startDate: new \DateTime('2025-01-01', new \DateTimeZone('UTC')),
    startTime: new \DateTime('2025-01-01 09:00', new \DateTimeZone('UTC')),
    repeatCount: 3,
    duration: 'PT1H',
    byDay: [DayOfWeek::MONDAY],
    byMonth: [Month::JANUARY, Month::MARCH, Month::JUNE],
);