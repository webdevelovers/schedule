<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

use const DATE_ATOM;

readonly class ScheduleOccurrence
{
    public DateTimeInterface $end;

    public function __construct(
        public DateTimeInterface $start,
        public DateInterval $duration,
        public DateTimeZone $timezone,
    ) {
        $immutableStart = $start instanceof DateTimeImmutable
            ? $start
            : DateTimeImmutable::createFromInterface($start);

        $this->end = $immutableStart->add($duration);
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        return [
            'start' => $this->start->format(DATE_ATOM),
            'end' => $this->end->format(DATE_ATOM),
            'duration' => $this->duration->format('%h:%I:%S'),
            'timezone' => $this->timezone->getName(),
        ];
    }

    public function __toString(): string
    {
        $startStr = $this->start->format('Y-m-d H:i');
        $endStr = $this->end->format('Y-m-d H:i');

        return $startStr . ' → ' . $endStr . ' (' . $this->timezone->getName() . ')';
    }
}
