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

    public static function fromNumber(int $month): self
    {
        return match ($month) {
            1  => self::JANUARY,
            2  => self::FEBRUARY,
            3  => self::MARCH,
            4  => self::APRIL,
            5  => self::MAY,
            6  => self::JUNE,
            7  => self::JULY,
            8  => self::AUGUST,
            9  => self::SEPTEMBER,
            10 => self::OCTOBER,
            11 => self::NOVEMBER,
            12 => self::DECEMBER,
            default => throw new \InvalidArgumentException('Invalid month number. Should be between 1 and 12.'),
        };
    }
}
