<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Enum;

use Cake\Chronos\ChronosDate;
use InvalidArgumentException;

enum DayOfWeek: string
{
    case MONDAY    = 'monday';
    case TUESDAY   = 'tuesday';
    case WEDNESDAY = 'wednesday';
    case THURSDAY  = 'thursday';
    case FRIDAY    = 'friday';
    case SATURDAY  = 'saturday';
    case SUNDAY    = 'sunday';

    /** @throws InvalidArgumentException */
    public static function fromDate(ChronosDate $date): self
    {
        $dayNum = (int) $date->format('N');

        if ($dayNum > 7 || $dayNum < 1) {
            throw new InvalidArgumentException('Invalid day number');
        }

        return match ($dayNum) {
            1 => self::MONDAY,
            2 => self::TUESDAY,
            3 => self::WEDNESDAY,
            4 => self::THURSDAY,
            5 => self::FRIDAY,
            6 => self::SATURDAY,
            7 => self::SUNDAY,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MONDAY     => 'schedule.day.monday',
            self::TUESDAY    => 'schedule.day.tuesday',
            self::WEDNESDAY  => 'schedule.day.wednesday',
            self::THURSDAY   => 'schedule.day.thursday',
            self::FRIDAY     => 'schedule.day.friday',
            self::SATURDAY   => 'schedule.day.saturday',
            self::SUNDAY     => 'schedule.day.sunday',
        };
    }
}
