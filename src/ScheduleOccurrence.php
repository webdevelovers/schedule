<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use DateInterval;
use DateTimeInterface;
use DateTimeZone;

use const DATE_ATOM;

readonly class ScheduleOccurrence
{
    public DateInterval $duration;

    public function __construct(
        public DateTimeInterface $start,
        public DateTimeInterface $end,
        public DateTimeZone $timezone,
        public bool $isHoliday,
        public string $scheduleIdentifier,
    ) {
        $this->duration = $start->diff($end);
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'start' => $this->start->format(DATE_ATOM),
            'end' => $this->end->format(DATE_ATOM),
            'duration' => $this->duration->format('P%yY%mM%dDT%hH%iM%sS'),
            'timezone' => $this->timezone->getName(),
            'isHoliday' => $this->isHoliday ? 'true' : 'false',
        ];
    }

    public function __toString(): string
    {
        $startStr = $this->start->format('d-m-Y H:i');
        $endStr = $this->end->format('d-m-Y H:i');

        $boundaries = $startStr . ' → ' . $endStr;
        $timezone = '(' . $this->timezone->getName() . ')';
        $festivity = 'festivo: ' . ($this->isHoliday ? 'si' : 'no');

        return $boundaries . ' ' . $timezone . ' - ' . $festivity;
    }
}
