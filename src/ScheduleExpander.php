<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use DateInterval;
use DateInvalidTimeZoneException;
use DateMalformedIntervalStringException;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Exception\ScheduleExpandException;
use WebDevelovers\Schedule\Holiday\HolidayProviderInterface;

use function array_map;
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
     * @throws ScheduleExpandException
     */
    public function expand(
        DateTimeInterface|null $from = null,
        DateTimeInterface|null $to = null,
        callable|null $extraFilter = null,
    ): Generator {
        $s = $this->schedule;
        $max = $this->maxOccurrences;

        try {
            $tz = $s->timezone ?? $from?->getTimezone() ?? new DateTimeZone(date_default_timezone_get());
        } catch (DateInvalidTimeZoneException $e) {
            throw new ScheduleExpandException($e->getMessage(), $e->getCode(), $e);
        }

        $start = $s->startDate;
        if (! $start) {
            return;
        }

        $isRecurring = $s->isRecurring();
        if ($isRecurring === false) {
            yield from $this->handleNonRecurring($s, $to, $extraFilter);

            return;
        }

        $current = $s->startTime
            ? ($s->startTime instanceof \DateTimeImmutable
                ? $s->startTime
                : \DateTimeImmutable::createFromInterface($s->startTime)
            )
                ->setDate(
                    (int) $start->format('Y'),
                    (int) $start->format('m'),
                    (int) $start->format('d')
                )
            : $start;

        $occurrences = 0;
        $repeatCount = $s->repeatCount ?? $max;
        try {
            $duration = $s->duration !== null ? new DateInterval($s->duration) : null;
            $interval = new DateInterval($s->repeatFrequency->toISO8601());
        } catch (DateMalformedIntervalStringException $e) {
            throw new ScheduleExpandException($e->getMessage(), $e->getCode(), $e);
        }

        $endDate = $s->endDate ?? $to;

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
            if (! empty($s->byDay) && ! in_array(DayOfWeek::fromDateTime($current), $s->byDay)) {
                $current = $current->add($interval);
                continue;
            }

            // byMonth filter
            if (! empty($s->byMonth) && ! in_array((int) $current->format('n'), array_map(static fn ($m) => $m->value, $s->byMonth))) {
                $current = $current->add($interval);
                continue;
            }

            // byMonthDay filter
            if (! empty($s->byMonthDay) && ! in_array((int) $current->format('j'), $s->byMonthDay)) {
                $current = $current->add($interval);
                continue;
            }

            // byMonthWeek filter
            if (! empty($s->byMonthWeek)) {
                $weekOfMonth = (int) ceil($current->format('j') / 7);
                $lastDayOfMonth = (clone $current)->modify('last day of this month');
                $weeksInMonth = (int) ceil($lastDayOfMonth->format('j') / 7);
                $negativeWeek = $weekOfMonth - ($weeksInMonth + 1);

                $allowedWeeks = $s->byMonthWeek;

                if (! in_array($weekOfMonth, $allowedWeeks, true) && ! in_array($negativeWeek, $allowedWeeks, true)) {
                    $current = $current->add($interval);
                    continue;
                }
            }

            // Holiday management
            if ($s->excludeHolidays === true && $this->holidaysProvider->isHoliday($current)) {
                $current = $current->add($interval);
                continue;
            }

            // Exceptions management: dates specifically not to include in the occurrences.
            $isExcept = false;
            foreach ($s->exceptDates as $except) {
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
                $tz,
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
        Schedule $s,
        DateTimeInterface|null $to,
        callable|null $extraFilter = null,
    ): Generator {
        if ($s->startDate === null) {
            return;
        }

        try {
            $duration = $s->duration !== null ? new DateInterval($s->duration) : null;
            if ($duration === null) {
                return;
            }

            $tz = $s->timezone ?? new DateTimeZone(date_default_timezone_get());
        } catch (DateMalformedIntervalStringException | DateInvalidTimeZoneException $e) {
            throw new ScheduleExpandException($e->getMessage(), $e->getCode(), $e);
        }

        if ($s->startTime) {
            $start = ($s->startTime instanceof \DateTimeImmutable
                ? $s->startTime
                : \DateTimeImmutable::createFromInterface($s->startTime)
            )->setDate(
                (int) $s->startDate->format('Y'),
                (int) $s->startDate->format('m'),
                (int) $s->startDate->format('d')
            );
        } else {
            $start = (clone $s->startDate);
        }

        $occurrence = new ScheduleOccurrence($start, $duration, $tz);

        if (
            ($extraFilter && ! $extraFilter($occurrence)) ||
            ($to && $start > $to)
        ) {
            return;
        }

        yield $occurrence;
    }
}
