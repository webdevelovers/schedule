<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use DateInterval;
use DateInvalidTimeZoneException;
use DateMalformedIntervalStringException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Exception\ScheduleExpandException;
use WebDevelovers\Schedule\Holiday\HolidayProviderInterface;

use function ceil;
use function date_default_timezone_get;
use function in_array;

readonly class ScheduleExpander
{
    public function __construct(
        private Schedule $schedule,
        private HolidayProviderInterface $holidaysProvider,
        private int $maxOccurrences = 100,
    ) {
    }

    /**
     * @param callable|null $extraFilter Used for additional runtime filtering.
     *
     * @return Generator<ScheduleOccurrence>
     *
     * @throws ScheduleExpandException|DateMalformedStringException
     */
    public function expand(
        DateTimeInterface|null $from = null,
        DateTimeInterface|null $to = null,
        callable|null $extraFilter = null,
    ): Generator {
        $schedule = $this->schedule;
        $max = $this->maxOccurrences;

        try {
            $timezone = $schedule->timezone ?? new DateTimeZone(date_default_timezone_get());
        } catch (DateInvalidTimeZoneException $exception) {
            throw new ScheduleExpandException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $start = $schedule->startDate;
        if (! $start) {
            return;
        }

        $isRecurring = $schedule->isRecurring();
        if ($isRecurring === false) {
            yield from $this->handleNonRecurring($schedule, $to, $extraFilter);

            return;
        }

        $current = $schedule->startTime
            ? ($schedule->startTime instanceof DateTimeImmutable
                ? $schedule->startTime
                : DateTimeImmutable::createFromInterface($schedule->startTime)
            )
                ->setDate(
                    (int) $start->format('Y'),
                    (int) $start->format('m'),
                    (int) $start->format('d'),
                )
            : $start;

        $occurrences = 0;
        $repeatCount = $schedule->repeatCount ?? $max;
        try {
            $duration = $schedule->duration !== null ? new DateInterval($schedule->duration) : null;
            $interval = new DateInterval($schedule->repeatFrequency->toISO8601());
        } catch (DateMalformedIntervalStringException $e) {
            throw new ScheduleExpandException($e->getMessage(), $e->getCode(), $e);
        }

        $endDate = $schedule->endDate ?? $to;

        while ($occurrences < $max && $occurrences < $repeatCount) {
            // Exit condition: endDate is present and reached, or the $to boundary is reached, or no duration|interval are specified
            if (
                ($endDate !== null && $current->format('Y-m-d') > $endDate->format('Y-m-d')) ||
                ($to && $current->format('Y-m-d') > $to->format('Y-m-d')) ||
                $duration === null
            ) {
                break;
            }

            // byDay filter
            if (
                ! empty($schedule->byDay) &&
                ! in_array(DayOfWeek::fromDateTime($current), $schedule->byDay)
            ) {
                $current = $current->add($interval);
                continue;
            }

            // byMonth filter
            if (
                ! empty($schedule->byMonth) &&
                ! in_array(
                    Month::fromNumber((int) $current->format('n')),
                    $schedule->byMonth,
                    true,
                )
            ) {
                $current = $current->add($interval);
                continue;
            }

            // byMonthDay filter
            if (
                ! empty($schedule->byMonthDay) &&
                ! in_array((int) $current->format('j'), $schedule->byMonthDay)
            ) {
                $current = $current->add($interval);
                continue;
            }

            // byMonthWeek filter
            if (! empty($schedule->byMonthWeek)) {
                $weekOfMonth = (int) ceil($current->format('j') / 7);
                $lastDayOfMonth = (clone $current)->modify('last day of this month');
                $weeksInMonth = (int) ceil($lastDayOfMonth->format('j') / 7);
                $negativeWeek = $weekOfMonth - ($weeksInMonth + 1);

                $allowedWeeks = $schedule->byMonthWeek;

                if (! in_array($weekOfMonth, $allowedWeeks, true) && ! in_array($negativeWeek, $allowedWeeks, true)) {
                    $current = $current->add($interval);
                    continue;
                }
            }

            // Holiday management
            if (
                $schedule->excludeHolidays === true &&
                $this->holidaysProvider->isHoliday($current)
            ) {
                $current = $current->add($interval);
                continue;
            }

            // Exceptions management: dates specifically not to include in the occurrences.
            $isExcept = false;
            foreach ($schedule->exceptDates as $except) {
                if (
                    $except->format('Y-m-d H:i') === $current->format('Y-m-d H:i') ||
                    $except->format('Y-m-d') === $current->format('Y-m-d')
                ) {
                    $isExcept = true;
                    break;
                }
            }

            if ($isExcept) {
                $current = $current->add($interval);
                continue;
            }

            $occurrence = new ScheduleOccurrence(
                clone $current,
                $duration,
                $timezone,
            );

            // custom callable filter, for runtime exceptions
            if ($extraFilter !== null && ! $extraFilter($occurrence)) {
                $current = $current->add($interval);
                continue;
            }

            yield $occurrence;

            $occurrences++;

            $current = $current->add($interval);
        }
    }

    /** @throws ScheduleExpandException */
    private function handleNonRecurring(
        Schedule $schedule,
        DateTimeInterface|null $to,
        callable|null $extraFilter = null,
    ): Generator {
        if ($schedule->startDate === null) {
            return;
        }

        try {
            $duration = $schedule->duration !== null ? new DateInterval($schedule->duration) : null;
            if ($duration === null) {
                return;
            }

            $timezone = $schedule->timezone ?? new DateTimeZone(date_default_timezone_get());
        } catch (DateMalformedIntervalStringException | DateInvalidTimeZoneException $e) {
            throw new ScheduleExpandException($e->getMessage(), $e->getCode(), $e);
        }

        if ($schedule->startTime) {
            $start = ($schedule->startTime instanceof DateTimeImmutable
                ? $schedule->startTime
                : DateTimeImmutable::createFromInterface($schedule->startTime)
            )->setDate(
                (int) $schedule->startDate->format('Y'),
                (int) $schedule->startDate->format('m'),
                (int) $schedule->startDate->format('d'),
            );
        } else {
            $start = (clone $schedule->startDate);
        }

        $occurrence = new ScheduleOccurrence($start, $duration, $timezone);

        if (
            ($extraFilter && ! $extraFilter($occurrence)) ||
            ($to && $start > $to)
        ) {
            return;
        }

        yield $occurrence;
    }
}
