<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use DateTimeZone;

interface ScheduleOccurrenceInterface
{
    public function getTimezone(): DateTimeZone;

    public function isHoliday(): bool;

    public function getScheduleIdentifier(): string;

    /** @return array<string, string|null> */
    public function toArray(): array;

    public function __toString(): string;
}
