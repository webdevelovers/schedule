<?php

use WebDevelovers\Schedule\Schedule;
use WebDevelovers\Schedule\Enum\ScheduleInterval;

/**
 * "An event taking place every day, from 09:00 to 10:00
 *  starting from the first of January, for three occurrences."
 *
 * in this case the occurrences will be:
 * - 02-01-2025 09:00 -> 02-01-2025 10:00
 * - 03-01-2025 09:00 -> 03-01-2025 10:00
 * - 04-01-2025 09:00 -> 04-01-2025 10:00
 *
 * if holidays are to be excluded, otherwise they will be:
 *
 * - 01-01-2025 09:00 -> 01-01-2025 10:00
 * - 02-01-2025 09:00 -> 02-01-2025 10:00
 * - 03-01-2025 09:00 -> 03-01-2025 10:00
 */

$schedule = new Schedule(
    repeatInterval: ScheduleInterval::DAILY,
    startDate: new \DateTime('2025-01-01', new \DateTimeZone('UTC')),
    startTime: new \DateTime('2025-01-01 09:00'),
    endTime: new \DateTime('2025-01-01 10:00'),
    repeatCount: 3,
);
