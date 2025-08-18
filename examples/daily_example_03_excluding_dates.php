<?php

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use WebDevelovers\Schedule\Schedule;
use WebDevelovers\Schedule\Enum\ScheduleInterval;

/**
 * "An event taking place daily, from 09:00 to 10:00,
 *  starting from the first of January, for three occurrences.
 *  But the second day of January has to be excluded"
 *
 * In this case the occurrences will be:
 * - 01-01-2025 09:00 -> 03-01-2025 10:00
 * - 03-01-2025 09:00 -> 03-01-2025 10:00
 * - 04-01-2025 09:00 -> 03-01-2025 10:00
 */

$schedule = new Schedule(
    repeatInterval: ScheduleInterval::DAILY,
    startDate: new ChronosDate('2025-01-01'),
    startTime: new ChronosTime('09:00'),
    endTimeOrDuration: new ChronosTime('10:00'),
    repeatCount: 3,
    exceptDates: [new ChronosDate('2025-01-02')],
);
