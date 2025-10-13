<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Enum;

enum ScheduleInterval: string
{
    case NONE = 'none';
    case DAILY = 'daily';
    case EVERY_WEEK = 'every_week';
    case EVERY_TWO_WEEKS = 'every_two_weeks';
    case EVERY_THREE_WEEKS = 'every_three_weeks';
    case EVERY_FOUR_WEEKS = 'every_four_weeks';
    case EVERY_MONTH = 'every_month';
    case EVERY_TWO_MONTHS = 'every_two_months';
    case EVERY_THREE_MONTHS = 'every_three_months';
    case EVERY_FOUR_MONTHS = 'every_four_months';
    case EVERY_SIX_MONTHS = 'every_six_months';
    case EVERY_YEAR = 'every_year';

    public function label(): string
    {
        return match ($this) {
            self::NONE                   => 'schedule.frequency.none',
            self::DAILY                  => 'schedule.frequency.daily',
            self::EVERY_WEEK             => 'schedule.frequency.every_week',
            self::EVERY_TWO_WEEKS        => 'schedule.frequency.every_two_weeks',
            self::EVERY_THREE_WEEKS      => 'schedule.frequency.every_three_weeks',
            self::EVERY_FOUR_WEEKS       => 'schedule.frequency.every_four_weeks',
            self::EVERY_MONTH            => 'schedule.frequency.every_month',
            self::EVERY_TWO_MONTHS       => 'schedule.frequency.every_two_months',
            self::EVERY_THREE_MONTHS     => 'schedule.frequency.every_three_months',
            self::EVERY_FOUR_MONTHS      => 'schedule.frequency.every_four_months',
            self::EVERY_SIX_MONTHS       => 'schedule.frequency.every_six_months',
            self::EVERY_YEAR             => 'schedule.frequency.every_year',
        };
    }

    public function toISO8601(): string
    {
        return match ($this) {
            self::NONE               => 'P0D',
            self::DAILY              => 'P1D',
            self::EVERY_WEEK         => 'P1W',
            self::EVERY_TWO_WEEKS    => 'P2W',
            self::EVERY_THREE_WEEKS  => 'P3W',
            self::EVERY_FOUR_WEEKS   => 'P4W',
            self::EVERY_MONTH        => 'P1M',
            self::EVERY_TWO_MONTHS   => 'P2M',
            self::EVERY_THREE_MONTHS => 'P3M',
            self::EVERY_FOUR_MONTHS  => 'P4M',
            self::EVERY_SIX_MONTHS   => 'P6M',
            self::EVERY_YEAR         => 'P1Y',
        };
    }

    public function equals(self|null $other): bool
    {
        return $other !== null && $this === $other;
    }
}
