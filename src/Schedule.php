<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use DateInterval;
use DateInvalidTimeZoneException;
use DateMalformedIntervalStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Frequency;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Exception\ScheduleException;
use WebDevelovers\Schedule\Exception\ScheduleValidationException;

use function array_map;
use function array_unique;
use function count;
use function is_int;

/**
 * Represents an abstract schedule pattern, which can be used to describe
 * recurring or non-recurring(single occurrence) events, such as courses, classes, meetings, appointments, etc.
 * This class does not provide occurrence expansion, but defines all possible
 * rules and exceptions that a calendarization can have.
 *
 * Inspired by https://schema.org/Schedule
 */
class Schedule
{
    private const string DEFAULT_TIMEZONE = 'UTC';
    public DateTimeZone $timezone;

    /**
     * @param Frequency $repeatFrequency The frequency of the occurrences (e.g., Daily/Weekly).
     * @param DateTimeInterface|null $startDate The first day of the occurrences (inclusive, day is considered).
     * @param DateTimeInterface|null $endDate The last day of the occurrences (inclusive, day is considered).
     * @param DateTimeInterface|null $startTime The time when each occurrence starts (time part is considered).
     * @param DateTimeInterface|null $endTime
     *   The time when each occurrence ends (time part is considered).
     *   If null and duration is provided, it will be automatically calculated.
     * @param int|null $repeatCount The maximum number of occurrences to generate.
     * @param string|null $duration
     *   Duration of each occurrence, in ISO8601 format (e.g. "PT1H30M").
     *   If null and endTime are provided, it will be inferred.
     * @param string|null $timezone The timezone (IANA standard) for all occurrences.
     * @param DayOfWeek[] $byDay Filters by days of the week (e.g., only Mondays and Wednesdays).
     * @param int[] $byMonthDay Filters by days of the month (e.g., 1st, 15th).
     * @param Month[] $byMonth Filters by months of the year (e.g., January, June).
     * @param int[] $byMonthWeek Filters by weeks within the month (e.g., 1 = first week, -1 = last week).
     * @param DateTimeInterface[] $exceptDates
     *   Array of excluded dates or datetimes. If only the date is set,
     *   all events on that date will be excluded; if datetime, only the
     *   matching event will be excluded.
     *
     * @throws ScheduleException
     */
    public function __construct(
        public Frequency $repeatFrequency,
        public DateTimeInterface|null $startDate = null,
        public DateTimeInterface|null $endDate = null,
        public DateTimeInterface|null $startTime = null,
        public DateTimeInterface|null $endTime = null,
        public int|null $repeatCount = null,
        public string|null $duration = null,
        public array $byDay = [],
        public array $byMonthDay = [],
        public array $byMonth = [],
        public array $byMonthWeek = [],
        public array $exceptDates = [],
        public bool $excludeHolidays = true,
        string|null $timezone = null,
    ) {
        // Semantic coherence: if both startTime and duration are set but endTime is missing, compute it
        if ($this->startTime !== null && $this->duration !== null && $this->endTime === null) {
            try {
                // Clone the startTime and add duration (ISO8601)
                $base = $this->startTime instanceof DateTimeImmutable
                    ? $this->startTime
                    : DateTimeImmutable::createFromInterface($this->startTime);

                $this->endTime = $base->add(new DateInterval($this->duration));
            } catch (Throwable $throwable) {
                throw new ScheduleException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }
        }

        // Likewise, if startTime and endTime are set but duration is missing, compute it
        if ($this->startTime === null || $this->endTime === null || $this->duration !== null) {
            return;
        }

        try {
            $this->duration = $this->startTime->diff($this->endTime)->format('PT%hH%iM%sS');
        } catch (Throwable $throwable) {
            throw new ScheduleException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        try {
            $this->timezone = new DateTimeZone($timezone ?? self::DEFAULT_TIMEZONE);
        } catch (DateInvalidTimeZoneException $e) {
            throw new ScheduleException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @throws ScheduleValidationException */
    public function validate(): void
    {
        $errors = [];

        // startDate has to be <= endDate
        if ($this->startDate && $this->endDate && $this->startDate > $this->endDate) {
            $errors[] = 'startDate must be before or equal to endDate.';
        }

        // startTime < endTime
        // TODO: for now occurrences occupying multiple days(es. an event that starts at 23:00 and ends at 01:00) are unsupported.
        if ($this->startTime && $this->endTime && $this->startTime >= $this->endTime) {
            $errors[] = 'startTime must be before endTime. For now occurrences occupying multiple days(es. an event that starts at 23:00 and ends at 01:00) are unsupported.';
        }

        if ($this->repeatCount !== null) {
            if ($this->repeatCount < 1) {
                $errors[] = 'repeatCount must be a positive integer.';
            }
        }

        // duration, if present, should be positive and valid
        if ($this->duration) {
            try {
                $interval = new DateInterval($this->duration);
                $isZero = ($interval->y === 0 && $interval->m === 0 && $interval->d === 0 && $interval->h === 0 && $interval->i === 0 && $interval->s === 0);
                if ($isZero) {
                    $errors[] = 'duration must be a non-zero interval.';
                }
            } catch (DateMalformedIntervalStringException) {
                $errors[] = 'duration is not a valid ISO8601 interval.';
            }
        }

        // byDay: check that all elements are instances of DayOfWeek
        foreach ($this->byDay as $d) {
            if (! $d instanceof DayOfWeek) {
                $errors[] = 'Each "byDay" element must be a DayOfWeek enum instance.';
                break;
            }
        }

        // byDay: check for duplicates
        $byDayValues = array_map(static fn ($d) => $d->value, $this->byDay);
        if (count(array_unique($byDayValues)) < count($byDayValues)) {
            $errors[] = 'byDay should not contain duplicates.';
        }

        // byMonth: check that all elements are instances of Month
        foreach ($this->byMonth as $m) {
            if ($m instanceof Month) {
                $errors[] = 'Each "byMonth" element must be a Month enum instance.';
                break;
            }
        }

        // byMonthDay: range 1..31 or -31..-1
        foreach ($this->byMonthDay as $d) {
            if (! is_int($d) || ($d < 1 && $d > -1) || $d > 31 || $d < -31) {
                $errors[] = 'byMonthDay must contain only integers in the range 1..31 or -31..-1.';
                break;
            }
        }

        // byMonthWeek: only integers, between 1 and 6 or -1 and -6, no zero
        foreach ($this->byMonthWeek as $w) {
            if (! is_int($w) || $w === 0 || $w < -6 || $w > 6) {
                $errors[] = 'byMonthWeek must contain only integer values between 1..6 o -1..-6 (0 is inadmissible)';
                break;
            }
        }

        // exceptDates must be between startDate and endDate
        if ($this->startDate && $this->endDate) {
            foreach ($this->exceptDates as $ex) {
                if ($ex < $this->startDate || $ex > $this->endDate) {
                    $errors[] = 'Some exceptDates are outside the range startDate/endDate.';
                    break;
                }
            }
        }

        if ($errors) {
            throw new ScheduleValidationException($errors);
        }
    }

    /** Returns true if the schedule is recurring (i.e., has a repeat frequency which is not "NONE"). */
    public function isRecurring(): bool
    {
        return $this->repeatFrequency !== Frequency::NONE;
    }

    /** @return array<string,array<int|string>|string|int|null> */
    public function asArray(): array
    {
        return [
            'startDate' => $this->startDate?->format(DateTimeInterface::ATOM),
            'endDate' => $this->endDate?->format(DateTimeInterface::ATOM),
            'startTime' => $this->startTime?->format('H:i:s'),
            'endTime' => $this->endTime?->format('H:i:s'),
            'duration' => $this->duration,
            'repeatFrequency' => $this->repeatFrequency->value,
            'repeatCount' => $this->repeatCount,
            'timezone' => $this->timezone->getName(),
            'byDay' => $this->byDay ? array_map(static fn (DayOfWeek $d) => $d->value, $this->byDay) : null,
            'byMonthDay' => $this->byMonthDay,
            'byMonth' => $this->byMonth ? array_map(static fn (Month $d) => $d->value, $this->byMonth) : null,
            'byMonthWeek' => $this->byMonthWeek,
            'exceptDates' => array_map(
                static fn (DateTimeInterface $dt) => $dt->format(DateTimeInterface::ATOM),
                $this->exceptDates,
            ),
        ];
    }
}
