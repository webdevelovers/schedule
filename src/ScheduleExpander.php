<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use DateInterval;
use DateInvalidTimeZoneException;
use DateMalformedIntervalStringException;
use DateTimeImmutable;
use DateTimeZone;
use Generator;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Exception\ScheduleException;
use WebDevelovers\Schedule\Exception\ScheduleExpandException;
use WebDevelovers\Schedule\Holiday\HolidayProviderInterface;

use function ceil;
use function date_default_timezone_get;
use function in_array;

readonly class ScheduleExpander
{
    public function __construct(
        private Schedule $schedule,
        private HolidayProviderInterface|null $holidaysProvider = null,
    ) {
    }

    /**
     * @return Generator<ScheduleOccurrence>
     *
     * @throws ScheduleExpandException
     */
    public function expand(): Generator {
        $schedule = $this->schedule;
        $timezone = self::getTimezone($schedule);

        $start = $schedule->startDate;
        if (! $start) {
            return;
        }

        if (! $schedule->isRecurring()) {
            yield from $this->handleNonRecurring($schedule);

            return;
        }

        $current = $start;
        $occurrences = 0;
        $repeatCount = $schedule->repeatCount;
        $duration = $schedule->duration !== null ? $schedule->duration : null;
        $interval = self::scheduleInterval($schedule);
        $endDate = $schedule->endDate;

        while ($repeatCount === null || $occurrences < $repeatCount) {
            // Exit condition: endDate is present and reached, or no duration|interval are specified
            if (
                ($endDate !== null && $current->greaterThan($endDate)) ||
                $duration === null
            ) {
                break;
            }

            if(self::byDayFilterIsApplied($schedule, $current)) {
                $current = $current->add($interval);
                continue;
            }

            if(self::byMonthFilterIsApplied($schedule, $current)) {
                $current = $current->add($interval);
                continue;
            }

            if(self::byMontDayFilterIsApplied($schedule, $current)) {
                $current = $current->add($interval);
                continue;
            }

            if(self::byMonthWeekFilterIsApplied($schedule, $current)) {
                $current = $current->add($interval);
                continue;
            }

            if(self::isExcludedDate($schedule, $current)) {
                $current = $current->add($interval);
                continue;
            }

            $startDT = self::composeStart($current, $schedule->startTime, $timezone);
            $endDT = self::composeEnd($startDT, $current, $schedule->startTime, $schedule->endTime, $schedule->duration, $timezone);

            if ($endDT === null) {
                $endDT = $startDT;
            }

            $isHoliday = $this->holidaysProvider && $this->holidaysProvider->isHoliday($current);

            yield new ScheduleOccurrence(
                start: $startDT,
                end: $endDT,
                timezone: $timezone,
                isHoliday: $isHoliday
            );

            $occurrences++;

            $current = $current->add($interval);
        }
    }

    private static function byDayFilterIsApplied(Schedule $schedule, ChronosDate $current): bool
    {
        return ! empty($schedule->byDay) && ! in_array(DayOfWeek::fromDate($current), $schedule->byDay);
    }

    private static function byMonthFilterIsApplied(Schedule $schedule, ChronosDate $current): bool
    {
        return  ! empty($schedule->byMonth) &&
                ! in_array(
                    Month::fromNumber((int) $current->format('n')),
                    $schedule->byMonth,
                    true,
                );
    }

    private static function byMontDayFilterIsApplied(Schedule $schedule, ChronosDate $current): bool
    {
        return ! empty($schedule->byMonthDay) && ! in_array((int) $current->format('j'), $schedule->byMonthDay);
    }

    private static function byMonthWeekFilterIsApplied(Schedule $schedule, ChronosDate $current): bool
    {
        if(empty($schedule->byMonthWeek)) {
            return false;
        }

        $firstDayOfMonth = (clone $current)->modify('first day of this month');
        $lastDayOfMonth  = (clone $current)->modify('last day of this month');

        $firstDow = (int) $firstDayOfMonth->format('N');
        $offset = $firstDow - 1;

        $dayOfMonth = (int) $current->format('j');
        $daysInMonth = (int) $lastDayOfMonth->format('j');

        $weekOfMonth = (int) ceil(($dayOfMonth + $offset) / 7);
        $weeksInMonth = (int) ceil(($daysInMonth + $offset) / 7);
        $negativeWeek = $weekOfMonth - ($weeksInMonth + 1);

        $allowedWeeks = $schedule->byMonthWeek;

        return ! in_array($weekOfMonth, $allowedWeeks, true) && ! in_array($negativeWeek, $allowedWeeks, true);
    }

    private static function isExcludedDate(Schedule $schedule, ChronosDate $current): bool
    {
        foreach ($schedule->exceptDates as $except) {
            if (
                $except->format('Y-m-d H:i') === $current->format('Y-m-d H:i') ||
                $except->format('Y-m-d') === $current->format('Y-m-d')
            ) {
                return true;
            }
        }

        return false;
    }

    /** @throws ScheduleExpandException */
    private function handleNonRecurring(
        Schedule $schedule,
    ): Generator {
        if ($schedule->startDate === null) {
            return;
        }

        try {
            $timezone = $schedule->timezone ?? new DateTimeZone(date_default_timezone_get());
        } catch (DateInvalidTimeZoneException $e) {
            throw new ScheduleExpandException($e->getMessage(), $e->getCode(), $e);
        }

        $currentDate = $schedule->startDate;

        $startDT = self::composeStart($currentDate, $schedule->startTime, $timezone);
        $endDT = self::composeEnd($startDT, $currentDate, $schedule->startTime, $schedule->endTime, $schedule->duration, $timezone) ?? $startDT;

        $isHoliday = $this->holidaysProvider && $this->holidaysProvider->isHoliday($currentDate);

        yield new ScheduleOccurrence($startDT, $endDT, $timezone, $isHoliday);
    }

    /** @throws ScheduleExpandException */
    private static function composeStart(ChronosDate $date, ChronosTime|null $time, DateTimeZone $tz): DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('m');
        $day = (int) $date->format('d');

        if ($time instanceof ChronosTime) {
            $t = $time->toDateTimeImmutable($tz);

            return $t->setDate($year, $month, $day);
        }

        try {
            return new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $tz);
        } catch (\DateMalformedStringException $e) {
            throw new ScheduleExpandException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private static function composeEnd(
        DateTimeImmutable $startDT,
        ChronosDate $date,
        ChronosTime|null $startTime,
        ChronosTime|null $endTime,
        DateInterval|null $duration,
        DateTimeZone $tz
    ): DateTimeImmutable|null {
        if ($duration !== null) {
            return $startDT->add($duration);
        }

        if ($endTime instanceof ChronosTime) {
            $endBase = $endTime->toDateTimeImmutable($tz)
                ->setDate(
                    (int) $date->format('Y'),
                    (int) $date->format('m'),
                    (int) $date->format('d'),
                );

            // If the end time is before the start time, add a day to the end date
            if ($startTime instanceof ChronosTime && $endTime->lessThan($startTime)) {
                return $endBase->add(new DateInterval('P1D'));
            }

            return $endBase;
        }

        return null;
    }

    /** @throws ScheduleExpandException */
    private static function getTimezone(Schedule $schedule): DateTimeZone
    {
        try {
            return $schedule->timezone ?? new DateTimeZone(date_default_timezone_get());
        } catch (DateInvalidTimeZoneException $exception) {
            throw new ScheduleExpandException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /** @throws ScheduleExpandException */
    private static function scheduleInterval(Schedule $schedule): DateInterval
    {
        try {
            return new DateInterval($schedule->repeatInterval->toISO8601());
        } catch (DateMalformedIntervalStringException $e) {
            throw new ScheduleExpandException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
