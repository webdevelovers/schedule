<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Enum;

enum Month: string
{
    case JANUARY = 'january';
    case FEBRUARY = 'february';
    case MARCH = 'march';
    case APRIL = 'april';
    case MAY = 'may';
    case JUNE = 'june';
    case JULY = 'july';
    case AUGUST = 'august';
    case SEPTEMBER = 'september';
    case OCTOBER = 'october';
    case NOVEMBER = 'november';
    case DECEMBER = 'december';

    public function label(): string
    {
        return match ($this) {
            self::JANUARY   => 'schedule.month.january',
            self::FEBRUARY  => 'schedule.month.february',
            self::MARCH     => 'schedule.month.march',
            self::APRIL     => 'schedule.month.april',
            self::MAY       => 'schedule.month.may',
            self::JUNE      => 'schedule.month.june',
            self::JULY      => 'schedule.month.july',
            self::AUGUST    => 'schedule.month.august',
            self::SEPTEMBER => 'schedule.month.september',
            self::OCTOBER   => 'schedule.month.october',
            self::NOVEMBER  => 'schedule.month.november',
            self::DECEMBER  => 'schedule.month.december',
        };
    }
}
