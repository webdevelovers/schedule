<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use DateInterval;
use DateTimeInterface;
use DateTimeZone;

readonly class ScheduleOccurrence
{
    public DateInterval $duration;

    public function __construct(
        public DateTimeInterface $start,
        public DateTimeInterface $end,
        public DateTimeZone $timezone,
        public bool $isHoliday,
    ) {
        $this->duration = $start->diff($end);
    }

    /**
     * @return array<string, string|null>
     */
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

        return $startStr . ' → ' . $endStr . ' (' . $this->timezone->getName() . ') - ' . ' festivo: ' . ($this->isHoliday ? 'si' : 'no');
    }
}
