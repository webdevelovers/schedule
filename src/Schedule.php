<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use DateInterval;
use DateInvalidTimeZoneException;
use DateMalformedIntervalStringException;
use DateTimeInterface;
use DateTimeZone;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Exception\ScheduleException;

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
readonly class Schedule
{
    private const string DEFAULT_TIMEZONE = 'UTC';

    public ChronosTime|null $endTime;
    public DateInterval|null $duration;
    public DateTimeZone $timezone;

    /**
     * @param ScheduleInterval          $repeatInterval
     * The interval between occurrences (e.g., Daily/Weekly).
     * @param ChronosDate|null          $startDate
     * The first day of the occurrences (inclusive).
     * @param ChronosDate|null          $endDate
     * The last day of the occurrences (inclusive).
     * @param ChronosTime|null          $startTime
     * The time when each occurrence starts
     * @param ChronosTime|DateInterval|string|null $endTimeOrDuration
     * @param int|null $repeatCount The maximum number of occurrences to generate.
     * @param DayOfWeek[] $byDay Filters by days of the week (e.g., only Mondays and Wednesdays).
     * @param int[] $byMonthDay Filters by days of the month (e.g., 1st, 15th).
     * @param Month[] $byMonth Filters by months of the year (e.g., January, June).
     * @param int[] $byMonthWeek Filters by weeks within the month (e.g., 1 = first week, -1 = last week).
     * @param DateTimeInterface[] $exceptDates
     *    Array of excluded dates or datetimes. If only the date is set,
     *    all events on that date will be excluded; if datetime, only the
     *    matching event will be excluded.
     * @param string|null $timezone The timezone (IANA standard) for all occurrences.
     *
     * @throws ScheduleException
     */
    public function __construct(
        public ScheduleInterval              $repeatInterval,
        public ChronosDate|null              $startDate         = null,
        public ChronosDate|null              $endDate           = null,
        public ChronosTime|null              $startTime         = null,
        ChronosTime|DateInterval|string|null $endTimeOrDuration = null,
        public int|null                      $repeatCount       = null,
        public array                         $byDay             = [],
        public array                         $byMonthDay        = [],
        public array                         $byMonth           = [],
        public array                         $byMonthWeek       = [],
        public array                         $exceptDates       = [],
        string|null                          $timezone          = null,
    ) {
        $this->setTimezone($timezone);
        $this->initEndTimeAndDuration($endTimeOrDuration);

        $this->validate();
    }

    /**
     * Gestisce la logica di parsing per determinare endTime e duration
     *
     * @param ChronosTime|DateInterval|string|null $endTimeOrDuration
     * @throws ScheduleException
     */
    private function initEndTimeAndDuration(
        ChronosTime|DateInterval|string|null $endTimeOrDuration,
    ): void
    {
        if($endTimeOrDuration === null) {
            $this->endTime = null;
            $this->duration = null;

            return;
        }

        if ($endTimeOrDuration instanceof DateInterval) {
            if (
                $endTimeOrDuration->y === 0 &&
                $endTimeOrDuration->m === 0 &&
                $endTimeOrDuration->d === 0 &&
                $endTimeOrDuration->h === 0 &&
                $endTimeOrDuration->i === 0 &&
                $endTimeOrDuration->s === 0
            ) {
                throw new ScheduleException('Duration interval cannot be zero.');
            }

            $this->duration = $endTimeOrDuration;
            if ($this->startTime !== null) {
                $startTimeAsDate = $this->startTime->toDateTimeImmutable($this->timezone);
                $this->endTime = new ChronosTime($startTimeAsDate->add($endTimeOrDuration));
            }

            return;
        }

        if ($endTimeOrDuration instanceof ChronosTime) {
            $this->endTime = $endTimeOrDuration;
            if ($this->startTime !== null) {
                $startTimeAsDate = $this->startTime->toDateTimeImmutable($this->timezone);
                $endTimeAsDate = $this->endTime->toDateTimeImmutable($this->timezone);
                //Endtime overflows in the next day
                if($this->endTime->lessThan($this->startTime)) {
                    $endTimeAsDate = $endTimeAsDate->add(new DateInterval('P1D'));
                }
                $this->duration = $endTimeAsDate->diff($startTimeAsDate);
            }
            return;
        }

        if (is_string($endTimeOrDuration) && strlen($endTimeOrDuration) > 0) {
            try {
                $duration = new DateInterval($endTimeOrDuration);
                if (
                    $duration->y === 0 &&
                    $duration->m === 0 &&
                    $duration->d === 0 &&
                    $duration->h === 0 &&
                    $duration->i === 0 &&
                    $duration->s === 0
                ) {
                    throw new ScheduleException('Duration interval cannot be zero.');
                }

                $this->duration = $duration;

                if($this->startTime !== null) {
                    $startTimeAsDate = $this->startTime->toDateTimeImmutable($this->timezone);
                    $endTimeAsDate = $startTimeAsDate->add($this->duration);
                    $this->endTime = new ChronosTime($endTimeAsDate);
                }
            } catch (DateMalformedIntervalStringException $e) {
                throw new ScheduleException("Duration as a string should be in ISO8601 format: " . $endTimeOrDuration, 0, $e);
            }
        }
    }

    /** Returns true if the schedule is recurring (i.e., has a repeat frequency which is not "NONE"). */
    public function isRecurring(): bool
    {
        return $this->repeatInterval !== ScheduleInterval::NONE;
    }

    /** @return array<string,array<int|string>|string|int|null> */
    public function asArray(): array
    {
        return [
            'startDate' => $this->startDate?->format(DateTimeInterface::ATOM),
            'endDate' => $this->endDate?->format(DateTimeInterface::ATOM),
            'startTime' => $this->startTime?->format('H:i:s'),
            'endTime' => $this->endTime?->format('H:i:s'),
            'duration' => $this->duration?->format('P%yY%mM%dDT%hH%iM%sS'),
            'repeatFrequency' => $this->repeatInterval->value,
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

    /** @throws ScheduleException */
    private function setTimezone(string|null $timezone): void
    {
        try {
            $this->timezone = new DateTimeZone($timezone ?? self::DEFAULT_TIMEZONE);
        } catch (DateInvalidTimeZoneException $e) {
            throw new ScheduleException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @throws ScheduleException */
    public function withStartDate(DateTimeInterface $startDate): self
    {
        return new self(
            $this->repeatInterval,
            $startDate,
            $this->endDate,
            $this->startTime,
            $this->endTime,
            $this->repeatCount,
            $this->byDay,
            $this->byMonthDay,
            $this->byMonth,
            $this->byMonthWeek,
            $this->exceptDates,
            $this->timezone->getName(),
        );
    }

    /** @throws ScheduleException */
    public function withEndDate(DateTimeInterface $endDate): self
    {
        return new self(
            $this->repeatInterval,
            $this->startDate,
            $endDate,
            $this->startTime,
            $this->endTime,
            $this->repeatCount,
            $this->duration,
            $this->byDay,
            $this->byMonthDay,
            $this->byMonth,
            $this->byMonthWeek,
            $this->exceptDates,
            $this->timezone->getName(),
        );
    }

    /** @throws ScheduleException */
    public function validate(): void
    {
        $this->validateStartAndEndDate();
        $this->validateStartAndEndTime();
        $this->validateRepeatCount();
        $this->validateByDay();
        $this->validateByMonth();
        $this->validateByMonthDay();
        $this->validateByMonthWeek();
        $this->validateExceptDates();
    }

    /** @throws ScheduleException */
    private function validateStartAndEndDate(): void
    {
        if ($this->startDate && $this->endDate && $this->endDate->lessThan($this->startDate)) {
            throw new ScheduleException('startDate must be before or equal to endDate.');
        }
    }

    private function validateStartAndEndTime(): void
    {
        //TODO: support start/endTime the overflow in the next day...but how?
    }

    /** @throws ScheduleException */
    private function validateRepeatCount(): void
    {
        if ($this->repeatCount !== null && $this->repeatCount < 1) {
            throw new ScheduleException('repeatCount must be a positive integer.');
        }
    }

    /** @throws ScheduleException */
    private function validateByDay(): void
    {
        foreach ($this->byDay as $index => $dayOfWeek) {
            if ($dayOfWeek instanceof DayOfWeek) {
                continue;
            }

            throw new ScheduleException('Each "byDay" element must be a DayOfWeek enum instance.');
        }

        // byDay: check for duplicates
        if (empty($errors) && count($this->byDay) > 0) {
            $byDayValues = array_map(static fn (DayOfWeek $dayOfWeek) => $dayOfWeek->value, $this->byDay);
            if (count(array_unique($byDayValues)) < count($byDayValues)) {
                throw new ScheduleException('byDay should not contain duplicates.');
            }
        }
    }

    /** @throws ScheduleException */
    private function validateByMonth(): void
    {
        foreach ($this->byMonth as $month) {
            if (! $month instanceof Month) {
                throw new ScheduleException('Each "byMonth" element must be a Month enum instance.');
            }
        }
    }

    /** @throws ScheduleException */
    private function validateByMonthDay(): void
    {
        foreach ($this->byMonthDay as $dayOfMonth) {
            if (
                ! is_int($dayOfMonth) ||
                $dayOfMonth === 0 ||
                $dayOfMonth > 31 ||
                $dayOfMonth < -31
            ) {
                throw new ScheduleException('byMonthDay must contain only integers in the range 1..31 or -31..-1.');
            }
        }
    }

    /** @throws ScheduleException */
    private function validateByMonthWeek(): void
    {
        foreach ($this->byMonthWeek as $week) {
            if (
                ! is_int($week) ||
                $week === 0 ||
                $week < -6 ||
                $week > 6
            ) {
                throw new ScheduleException('byMonthWeek must contain only integer values between 1..6 o -1..-6 (0 is inadmissible)');
            }
        }
    }

    /** @throws ScheduleException */
    private function validateExceptDates(): void
    {
        foreach ($this->exceptDates as $index => $exceptDate) {
            if (! $exceptDate instanceof DateTimeInterface) {
                throw new ScheduleException('Each "exceptDates" element must be an instance of DateTimeInterface.');
            }
        }

        if ($this->startDate && $this->endDate) {
            foreach ($this->exceptDates as $ex) {
                if ($ex < $this->startDate || $ex > $this->endDate) {
                    throw new ScheduleException('Some exceptDates are outside the range startDate/endDate.');
                }
            }
        }
    }
}
