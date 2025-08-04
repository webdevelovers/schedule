<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Enum;

enum Frequency: string
{
    case NONE = 'none';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case EVERY_TWO_WEEKS = 'every_two_weeks';
    case EVERY_THREE_WEEKS = 'every_three_weeks';
    case EVERY_FOUR_WEEKS = 'every_four_weeks';
    case MONTHLY = 'monthly';
    case EVERY_TWO_MONTHS = 'every_two_months';
    case EVERY_THREE_MONTHS = 'every_three_months';
    case EVERY_FOUR_MONTHS = 'every_four_months';
    case EVERY_SIX_MONTHS = 'every_six_months';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::NONE               => 'schedule.frequency.none',
            self::DAILY              => 'schedule.frequency.daily',
            self::WEEKLY             => 'schedule.frequency.weekly',
            self::EVERY_TWO_WEEKS    => 'schedule.frequency.every_two_weeks',
            self::EVERY_THREE_WEEKS  => 'schedule.frequency.every_three_weeks',
            self::EVERY_FOUR_WEEKS   => 'schedule.frequency.every_four_weeks',
            self::MONTHLY            => 'schedule.frequency.monthly',
            self::EVERY_TWO_MONTHS   => 'schedule.frequency.every_two_months',
            self::EVERY_THREE_MONTHS => 'schedule.frequency.every_three_months',
            self::EVERY_FOUR_MONTHS  => 'schedule.frequency.every_four_months',
            self::EVERY_SIX_MONTHS   => 'schedule.frequency.every_six_months',
            self::YEARLY             => 'schedule.frequency.yearly',
        };
    }

    public function toISO8601(): string
    {
        return match ($this) {
            self::NONE               => 'P0D',
            self::DAILY              => 'P1D',
            self::WEEKLY             => 'P1W',
            self::EVERY_TWO_WEEKS    => 'P2W',
            self::EVERY_THREE_WEEKS  => 'P3W',
            self::EVERY_FOUR_WEEKS   => 'P4W',
            self::MONTHLY            => 'P1M',
            self::EVERY_TWO_MONTHS   => 'P2M',
            self::EVERY_THREE_MONTHS => 'P3M',
            self::EVERY_FOUR_MONTHS  => 'P4M',
            self::EVERY_SIX_MONTHS   => 'P6M',
            self::YEARLY             => 'P1Y',
        };
    }

    public function equals(self|null $other): bool
    {
        return $other !== null && $this === $other;
    }
}
