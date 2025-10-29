<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Enum;

enum UnitOfTime: string
{
    case SECONDS = 'seconds';
    case MINUTES = 'minutes';
    case HOURS = 'hours';
    case DAYS = 'days';
    case WEEKS = 'weeks';
    case MONTHS = 'months';
    case YEARS = 'years';

    public function label(): string
    {
        return match ($this) {
            self::SECONDS => 'schedule.unit_of_time.seconds',
            self::MINUTES => 'schedule.unit_of_time.minutes',
            self::HOURS => 'schedule.unit_of_time.hours',
            self::DAYS => 'schedule.unit_of_time.days',
            self::WEEKS => 'schedule.unit_of_time.weeks',
            self::MONTHS => 'schedule.unit_of_time.months',
            self::YEARS => 'schedule.unit_of_time.years',
        };
    }
}
