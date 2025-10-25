<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use InvalidArgumentException;

use WebDevelovers\Schedule\Enum\UnitOfTime;
use function strlen;

class DateUtils
{
    /**
     * Converts a numeric value into a string duration in "hh:mm" format.
     * $value is a float in a specified $unit (seconds|minutes|hours|days).
     */
    public static function toHoursAndMinutes(float $value, UnitOfTime $unitOfTime): string
    {
        $seconds = self::convertUnitOfTimeIntoSeconds($value, $unitOfTime);

        $sign = $seconds < 0 ? '-' : '';
        $secondsAbs = abs($seconds);

        // arrotonda ai minuti
        $minutesFloat = $secondsAbs / 60.0;
        $totalMinutes = (int) floor($minutesFloat + 0.5);
        $hours = (int) floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }

    /**
     * Parse a string in hh:mm format into a float value in the specified unit.
     * $unit can be: seconds|minutes|hours|days.
     */
    public static function fromHoursAndMinutes(string $hhmm, UnitOfTime $unitOfTime): float
    {
        $trim = trim($hhmm);
        if ($trim === '') {
            throw new InvalidArgumentException('Empty hh:mm string.');
        }

        $sign = 1.0;
        if ($trim[0] === '-') {
            $sign = -1.0;
            $trim = substr($trim, 1);
        } elseif ($trim[0] === '+') {
            $trim = substr($trim, 1);
        }

        $parts = explode(':', $trim);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Invalid hh:mm format.');
        }

        [$hStr, $mStr] = $parts;
        if ($hStr === '' || $mStr === '' || !ctype_digit($hStr) || !ctype_digit($mStr)) {
            throw new InvalidArgumentException('Invalid hh:mm numeric parts.');
        }

        $hours = (int) $hStr;
        $minutes = (int) $mStr;

        if ($minutes < 0 || $minutes > 59) {
            throw new InvalidArgumentException('Minutes must be between 00 and 59.');
        }

        $totalSeconds = ($hours * 3600) + ($minutes * 60);
        $totalSeconds *= $sign;

        return self::convertSecondsIntoUnitOfTime((float) $totalSeconds, $unitOfTime);
    }

    /**
     * Converts a value in the specified unit to seconds.
     * $unit: seconds|minutes|hours|days
     */
    public static function convertUnitOfTimeIntoSeconds(float $value, UnitOfTime $unitOfTime): float
    {
        return match ($unitOfTime) {
            UnitOfTime::SECONDS => $value,
            UnitOfTime::MINUTES => $value * 60.0,
            UnitOfTime::HOURS   => $value * 3600.0,
            UnitOfTime::DAYS    => $value * 86400.0,
            UnitOfTime::WEEKS   => $value * 7.0 * 86400.0,
            UnitOfTime::MONTHS  => $value * 30.0 * 86400.0,
            UnitOfTime::YEARS   => $value * 365.0 * 86400.0,
        };
    }

    /**
     * Converts a value in seconds to the specified unit.
     * $unit: seconds|minutes|hours|days
     */
    public static function convertSecondsIntoUnitOfTime(float $seconds, UnitOfTime $unitOfTime): float
    {
        return match ($unitOfTime) {
            UnitOfTime::SECONDS => $seconds,
            UnitOfTime::MINUTES => $seconds / 60.0,
            UnitOfTime::HOURS   => $seconds / 3600.0,
            UnitOfTime::DAYS    => $seconds / 86400.0,
            UnitOfTime::WEEKS   => $seconds / (7.0 * 86400.0),
            UnitOfTime::MONTHS  => $seconds / (30.0 * 86400.0),
            UnitOfTime::YEARS   => $seconds / (365.0 * 86400.0),
        };
    }
}
