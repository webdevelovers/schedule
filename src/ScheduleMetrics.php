<?php

namespace WebDevelovers\Schedule;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use WebDevelovers\Schedule\Enum\UnitOfTime;
use WebDevelovers\Schedule\Exception\ScheduleExpandException;
use WebDevelovers\Schedule\Holiday\HolidayProviderInterface;

class ScheduleMetrics
{
    /** @throws ScheduleExpandException */
    public static function seconds(
        ScheduleAggregate|Schedule $schedule,
        HolidayProviderInterface $holidayProvider,
        DateTimeInterface|null $fromDate = null,
        DateTimeInterface|null $toDate = null
    ): int
    {
        $total = 0;

        if ($schedule instanceof ScheduleAggregate) {
            $generator = ScheduleExpander::expandAggregate($schedule, $holidayProvider);
        } else {
            $generator = ScheduleExpander::expand($schedule, $holidayProvider);
        }

        foreach ($generator as $occurrence) {
            $occurrenceStart = $occurrence->start;
            $occurrenceEnd = $occurrence->end;

            if (
                ($fromDate !== null && $occurrenceEnd !== null && $occurrenceEnd < $fromDate) ||
                ($toDate !== null && $occurrenceStart !== null && $occurrenceStart > $toDate)
            ) {
                continue;
            }

            $total += self::intervalToSeconds($occurrence->duration);
        }

        return $total;
    }

    /** @throws ScheduleExpandException */
    public static function minutes(
        ScheduleAggregate|Schedule $schedule,
        HolidayProviderInterface $holidayProvider,
        DateTimeInterface|null $fromDate = null,
        DateTimeInterface|null $toDate = null
    ): float
    {
        $seconds = self::seconds($schedule, $holidayProvider, $fromDate, $toDate);
        if($seconds === 0) {
            return 0;
        }

        return DateUtils::convertSecondsIntoUnitOfTime($seconds, UnitOfTime::MINUTES);
    }

    /** @throws ScheduleExpandException */
    public static function hours(
        ScheduleAggregate|Schedule $schedule,
        HolidayProviderInterface $holidayProvider,
        DateTimeInterface|null $fromDate = null,
        DateTimeInterface|null $toDate = null
    ): float
    {
        $seconds = self::seconds($schedule, $holidayProvider, $fromDate, $toDate);
        if($seconds === 0) {
            return 0;
        }

        return DateUtils::convertSecondsIntoUnitOfTime($seconds, UnitOfTime::HOURS);
    }

    /** @throws ScheduleExpandException */
    public static function days(
        ScheduleAggregate|Schedule $schedule,
        HolidayProviderInterface $holidayProvider,
        DateTimeInterface|null $fromDate = null,
        DateTimeInterface|null $toDate = null
    ): float
    {
        $seconds = self::seconds($schedule, $holidayProvider, $fromDate, $toDate);
        if ($seconds === 0) {
            return 0;
        }

        return DateUtils::convertSecondsIntoUnitOfTime($seconds, UnitOfTime::DAYS);
    }

    private static function intervalToSeconds(DateInterval $interval): int
    {
        $start = new DateTimeImmutable('@0');
        $end = $start->add($interval);

        return max(0, $end->getTimestamp() - $start->getTimestamp());
    }
}