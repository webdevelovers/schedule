<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use DateInterval;
use DateMalformedIntervalStringException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Generator;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Exception\ScheduleExpandException;
use WebDevelovers\Schedule\Holiday\HolidayProviderInterface;

use function array_splice;
use function ceil;
use function count;
use function in_array;
use function sprintf;

readonly class ScheduleExpander
{
    /**
     * Expands a ScheduleAggregate into all occurrences from all schedules
     *
     * @return Generator<ScheduleOccurrenceInterface>
     *
     * @throws ScheduleExpandException
     */
    public static function expandAggregate(
        ScheduleAggregate $aggregate,
        HolidayProviderInterface|null $holidaysProvider = null,
        ChronosDate|null $from = null,
        ChronosDate|null $to = null,
        callable|null $filter = null,
    ): Generator {
        $index = 0;
        foreach ($aggregate->all() as $schedule) {
            foreach (self::expand($schedule, $holidaysProvider, $from, $to, $filter) as $occurrence) {
                $key = $index;

                yield $key => $occurrence;

                $index++;
            }
        }
    }

    /**
     * Expands a ScheduleAggregate into all occurrences, sorted by start datetime.
     * Returns a generator for memory efficiency while maintaining global sort order.
     * Uses k-way merge algorithm. Guarantees unique occurrences.
     *
     * @return Generator<ScheduleOccurrenceInterface>
     *
     * @throws ScheduleExpandException
     */
    public static function expandAggregateSorted(
        ScheduleAggregate $aggregate,
        HolidayProviderInterface|null $holidaysProvider = null,
        bool $ascending = true,
        bool $unique = true,
        ChronosDate|null $from = null,
        ChronosDate|null $to = null,
        callable|null $filter = null,
    ): Generator {
        $generators = [];
        $values = [];

        // Initialize: create generators and get the first value from each
        foreach ($aggregate->all() as $schedule) {
            $generator = self::expand($schedule, $holidaysProvider, $from, $to, $filter);
            if (! $generator->valid()) {
                continue;
            }

            $generators[] = $generator;
            $values[] = $generator->current();
        }

        $seen = [];

        // K-way merge
        while (count($generators) > 0) {
            // Find the index of the minimum (or maximum if descending) value
            $minIndex = 0;
            for ($i = 1; $i < count($values); $i++) {
                $comparison = self::occurrenceSortKey($values[$i]) <=> self::occurrenceSortKey($values[$minIndex]);
                if ((! $ascending || $comparison >= 0) && ($ascending || $comparison <= 0)) {
                    continue;
                }

                $minIndex = $i;
            }

            $current = $values[$minIndex];

            // Check if we've already seen this occurrence
            $key = self::occurrenceKey($current);
            if (! $unique || ! isset($seen[$key])) {
                yield $current;

                if ($unique) {
                    $seen[$key] = true;
                }
            }

            // Advance the generator that produced the minimum
            $generators[$minIndex]->next();

            // If the generator has more values, update; otherwise remove it
            if ($generators[$minIndex]->valid()) {
                $values[$minIndex] = $generators[$minIndex]->current();
            } else {
                array_splice($generators, $minIndex, 1);
                array_splice($values, $minIndex, 1);
            }
        }
    }

    /**
     * @return Generator<ScheduleOccurrenceInterface>
     *
     * @throws ScheduleExpandException
     */
    public static function expand(
        Schedule $schedule,
        HolidayProviderInterface|null $holidayProvider = null,
        ChronosDate|null $from = null,
        ChronosDate|null $to = null,
        callable|null $filter = null,
    ): Generator {
        $start = self::calculateStart($schedule->startDate, $from);
        if ($start === null) {
            return;
        }

        if (! $schedule->isRecurring()) {
            yield from self::handleNonRecurring($schedule, $holidayProvider);

            return;
        }

        if ($schedule->startTime === null && $schedule->endTime === null && $schedule->duration === null) {
            if ($schedule->repeatCount === null && $schedule->endDate === null) {
                return;
            }

            yield from self::expandDateOnly($schedule, $holidayProvider, $from, $to, $filter);

            return;
        }

        // For recurring schedules, we need either endTime or duration to create occurrences
        if (
            (
                $schedule->startTime !== null && ($schedule->endTime === null && $schedule->duration === null)
            ) || (
                ($schedule->endTime !== null || $schedule->duration !== null) && $schedule->startTime === null
            )
        ) {
            return;
        }

        $timezone = $schedule->timezone;
        $current = $start;
        $occurrences = 0;
        $repeatCount = $schedule->repeatCount;
        $interval = self::scheduleInterval($schedule);
        $endDate = self::calculateEnd($schedule->endDate, $to);
        $index = 0;

        while ($repeatCount === null || $occurrences < $repeatCount) {
            // Exit condition: endDate is present and reached
            if ($endDate !== null && $current->greaterThan($endDate)) {
                break;
            }

            if (! self::shouldIncludeDate($schedule, $current)) {
                $current = $current->add($interval);
                continue;
            }

            $startDT = self::composeStart($current, $schedule->startTime, $timezone);
            $endDT = self::composeEnd($startDT, $current, $schedule->startTime, $schedule->endTime, $schedule->duration, $timezone) ?? $startDT;
            $isHoliday = $holidayProvider && $holidayProvider->isHoliday($current);

            $occurrence = new ScheduleDateTimeOccurrence(
                start: $startDT,
                end: $endDT,
                timezone: $timezone,
                isHoliday: $isHoliday,
                scheduleIdentifier: $schedule->identifier,
            );
            if ($filter === null || $filter($occurrence, $schedule, $index)) {
                yield $occurrence;
            }

            $occurrences++;
            $index++;
            $current = $current->add($interval);
        }
    }

    /**
     * @return Generator<ScheduleOccurrenceInterface>
     *
     * @throws ScheduleExpandException
     */
    private static function expandDateOnly(
        Schedule $schedule,
        HolidayProviderInterface|null $holidayProvider,
        ChronosDate|null $from,
        ChronosDate|null $to,
        callable|null $filter,
    ): Generator {
        $start = self::calculateStart($schedule->startDate, $from);
        if ($start === null) {
            return;
        }

        $current = $start;
        $occurrences = 0;
        $repeatCount = $schedule->repeatCount;
        $interval = self::scheduleInterval($schedule);
        $endDate = self::calculateEnd($schedule->endDate, $to);
        $index = 0;

        while ($repeatCount === null || $occurrences < $repeatCount) {
            if ($endDate !== null && $current->greaterThan($endDate)) {
                break;
            }

            if (! self::shouldIncludeDate($schedule, $current)) {
                $current = $current->add($interval);
                continue;
            }

            $isHoliday = $holidayProvider && $holidayProvider->isHoliday($current);

            $occurrence = new ScheduleDateOccurrence(
                date: $current,
                timezone: $schedule->timezone,
                isHoliday: $isHoliday,
                scheduleIdentifier: $schedule->identifier,
            );

            if ($filter === null || $filter($occurrence, $schedule, $index)) {
                yield $occurrence;
            }

            $occurrences++;
            $index++;
            $current = $current->add($interval);
        }
    }

    private static function shouldIncludeDate(Schedule $schedule, ChronosDate $current): bool
    {
        $isExplicitlyIncluded = self::isIncludedDate($schedule, $current);
        if ($isExplicitlyIncluded) {
            return true;
        }

        if (self::byDayFilterIsApplied($schedule, $current)) {
            return false;
        }

        if (self::byMonthFilterIsApplied($schedule, $current)) {
            return false;
        }

        if (self::byMontDayFilterIsApplied($schedule, $current)) {
            return false;
        }

        if (self::byMonthWeekFilterIsApplied($schedule, $current)) {
            return false;
        }

        return ! self::isExcludedDate($schedule, $current);
    }

    private static function calculateStart(ChronosDate|null $startDate, ChronosDate|null $from): ChronosDate|null
    {
        if ($startDate !== null && $from !== null) {
            return $startDate->greaterThan($from) ? $startDate : $from;
        }

        if ($startDate === null && $from === null) {
            return null;
        }

        return $from ?? $startDate;
    }

    private static function calculateEnd(ChronosDate|null $endDate, ChronosDate|null $to): ChronosDate|null
    {
        if ($endDate !== null && $to !== null) {
            return $endDate->lessThan($to) ? $endDate : $to;
        }

        if ($endDate === null && $to === null) {
            return null;
        }

        return $to ?? $endDate;
    }

    private static function byDayFilterIsApplied(Schedule $schedule, ChronosDate $current): bool
    {
        return ! empty($schedule->byDay) && ! in_array(DayOfWeek::fromDate($current), $schedule->byDay);
    }

    private static function byMonthFilterIsApplied(Schedule $schedule, ChronosDate $current): bool
    {
        return ! empty($schedule->byMonth) &&
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
        if (empty($schedule->byMonthWeek)) {
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

    private static function isIncludedDate(Schedule $schedule, ChronosDate $current): bool
    {
        foreach ($schedule->includeDates as $include) {
            if (
                $include->format('Y-m-d H:i') === $current->format('Y-m-d H:i') ||
                $include->format('Y-m-d') === $current->format('Y-m-d')
            ) {
                return true;
            }
        }

        return false;
    }

    /** @throws ScheduleExpandException */
    private static function handleNonRecurring(
        Schedule $schedule,
        HolidayProviderInterface|null $holidaysProvider = null,
    ): Generator {
        if ($schedule->startDate === null) {
            return;
        }

        $timezone = $schedule->timezone;
        $currentDate = $schedule->startDate;
        $isHoliday = $holidaysProvider && $holidaysProvider->isHoliday($currentDate);

        if ($schedule->startTime === null && $schedule->endTime === null && $schedule->duration === null) {
            yield new ScheduleDateOccurrence(
                date: $currentDate,
                timezone: $timezone,
                isHoliday: $isHoliday,
                scheduleIdentifier: $schedule->identifier,
            );

            return;
        }

        if (($schedule->endTime !== null || $schedule->duration !== null) && $schedule->startTime === null) {
            return;
        }

        $startDT = self::composeStart($currentDate, $schedule->startTime, $timezone);
        $endDT = self::composeEnd(
            $startDT,
            $currentDate,
            $schedule->startTime,
            $schedule->endTime,
            $schedule->duration,
            $timezone
        ) ?? $startDT;

        yield new ScheduleDateTimeOccurrence(
            start: $startDT,
            end: $endDT,
            timezone: $timezone,
            isHoliday: $isHoliday,
            scheduleIdentifier: $schedule->identifier,
        );
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
        } catch (DateMalformedStringException $e) {
            throw new ScheduleExpandException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private static function composeEnd(
        DateTimeImmutable $startDT,
        ChronosDate $date,
        ChronosTime|null $startTime,
        ChronosTime|null $endTime,
        DateInterval|null $duration,
        DateTimeZone $tz,
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
    private static function scheduleInterval(Schedule $schedule): DateInterval
    {
        try {
            return new DateInterval($schedule->repeatInterval->toISO8601());
        } catch (DateMalformedIntervalStringException $e) {
            throw new ScheduleExpandException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** Creates a unique key for an occurrence based on its temporal identity */
    private static function occurrenceKey(ScheduleOccurrenceInterface $occurrence): string
    {
        if ($occurrence instanceof ScheduleDateOccurrence) {
            return 'date|' . $occurrence->date->format('Y-m-d');
        }

        if ($occurrence instanceof ScheduleDateTimeOccurrence) {
            return 'datetime|' . $occurrence->start->format('Y-m-d H:i:s') . '|' . $occurrence->end->format('Y-m-d H:i:s');
        }

        return 'unknown';
    }

    private static function occurrenceSortKey(ScheduleOccurrenceInterface $occurrence): string
    {
        if ($occurrence instanceof ScheduleDateOccurrence) {
            return $occurrence->date->format('Y-m-d') . ' 00:00:00';
        }

        if ($occurrence instanceof ScheduleDateTimeOccurrence) {
            return $occurrence->start->format('Y-m-d H:i:s');
        }

        return '';
    }

}
