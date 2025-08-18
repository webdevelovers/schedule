<?php

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use WebDevelovers\Schedule\Schedule;
use WebDevelovers\Schedule\Enum\ScheduleInterval;

/**
 * "An event taking place every day, from 09:00 to 10:00
 *  starting from the first of January, for three occurrences."
 *
 * in this case the occurrences (if holidays are to be excluded) will be:
 * - 02-01-2025 09:00 -> 02-01-2025 10:00
 * - 03-01-2025 09:00 -> 03-01-2025 10:00
 * - 04-01-2025 09:00 -> 04-01-2025 10:00
 *
 *  otherwise they will be:
 * - 01-01-2025 09:00 -> 01-01-2025 10:00
 * - 02-01-2025 09:00 -> 02-01-2025 10:00
 * - 03-01-2025 09:00 -> 03-01-2025 10:00
 */

$schedule = new Schedule(
    repeatInterval: ScheduleInterval::DAILY,
    startDate: new ChronosDate('2025-01-01'),
    startTime: new ChronosTime('09:00'),
    endTimeOrDuration: new ChronosTime('10:00'),
    repeatCount: 3,
);
