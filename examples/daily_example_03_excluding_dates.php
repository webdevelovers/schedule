<?php

use WebDevelovers\Schedule\Schedule;
use WebDevelovers\Schedule\Enum\Frequency;

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
    repeatFrequency: Frequency::DAILY,
    startDate: new \DateTime('2025-01-01', new \DateTimeZone('UTC')),
    startTime: new \DateTime('2025-01-01 09:00', new \DateTimeZone('UTC')),
    endTime: new \DateTime('2025-01-01 10:00', new \DateTimeZone('UTC')),
    repeatCount: 3,
    exceptDates: [new \DateTime('2025-01-02', new \DateTimeZone('UTC'))],
);
