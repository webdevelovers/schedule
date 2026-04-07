<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use Cake\Chronos\ChronosDate;
use DateTimeZone;

readonly class ScheduleDateOccurrence implements ScheduleOccurrenceInterface
{
    public function __construct(
        public ChronosDate $date,
        public DateTimeZone $timezone,
        public bool $isHoliday,
        public string $scheduleIdentifier,
    ) {
    }

    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function isHoliday(): bool
    {
        return $this->isHoliday;
    }

    public function getScheduleIdentifier(): string
    {
        return $this->scheduleIdentifier;
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'type' => 'date',
            'date' => $this->date->format('Y-m-d'),
            'timezone' => $this->timezone->getName(),
            'isHoliday' => $this->isHoliday ? 'true' : 'false',
            'scheduleIdentifier' => $this->scheduleIdentifier,
        ];
    }

    public function __toString(): string
    {
        return $this->date->format('d-m-Y') . ' (' . $this->timezone->getName() . ') - festivo: ' . ($this->isHoliday ? 'si' : 'no');
    }
}
