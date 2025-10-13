<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use DateInterval;
use DateInvalidTimeZoneException;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use Throwable;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Exception\ScheduleException;

use function array_key_exists;
use function array_map;
use function array_unique;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Represents an abstract schedule pattern, which can be used to describe
 * recurring or non-recurring (single occurrence) events, such as courses, classes, meetings, appointments, etc.
 * This class does not provide occurrence expansion, but defines all possible
 * rules and exceptions that a calendarization system can have.
 *
 * @see https://schema.org/Schedule
 */
class Schedule implements JsonSerializable
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
     * The time when each occurrence ends or the duration of the occurrences.
     * @param int|null $repeatCount The maximum number of requested occurrences.
     * @param DayOfWeek[] $byDay Filters by days of the week (e.g., only Mondays and Wednesdays).
     * @param int[] $byMonthDay Filters by days of the month (e.g., 1st, 15th).
     * @param Month[] $byMonth Filters by months of the year (e.g., January, June).
     * @param int[] $byMonthWeek Filters by weeks within the month (e.g., 1 = first week, -1 = last week).
     * @param ChronosDate[] $exceptDates
     *    Array of excluded dates or datetimes. If only the date is set,
     *    all events on that date will be excluded; if datetime, only the
     *    matching event will be excluded.
     * @param string|null $timezone The timezone (IANA standard) for all occurrences.
     *
     * @throws ScheduleException
     */
    public function __construct(
        public ScheduleInterval $repeatInterval,
        public ChronosDate|null $startDate = null,
        public ChronosDate|null $endDate = null,
        public ChronosTime|null $startTime = null,
        ChronosTime|DateInterval|string|null $endTimeOrDuration = null,
        public int|null $repeatCount = null,
        public array $byDay = [],
        public array $byMonthDay = [],
        public array $byMonth = [],
        public array $byMonthWeek = [],
        public array $exceptDates = [],
        string|null $timezone = null,
    ) {
        $this->setTimezone($timezone);
        $this->initEndTimeAndDuration($endTimeOrDuration);

        $this->validateStartAndEndDate();
        $this->validateRepeatCount();
        $this->validateByDay();
        $this->validateByMonth();
        $this->validateByMonthDay();
        $this->validateByMonthWeek();
        $this->validateExceptDates();
    }

    /** @throws ScheduleException */
    private function initEndTimeAndDuration(
        ChronosTime|DateInterval|string|null $endTimeOrDuration,
    ): void {
        try {
            [$this->endTime, $this->duration] = DateUtils::endTimeAndDurationFromParam($endTimeOrDuration, $this->startTime);
        } catch (InvalidArgumentException $e) {
            throw new ScheduleException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function isRecurring(): bool
    {
        return $this->repeatInterval !== ScheduleInterval::NONE;
    }

    public function toArray(): array
    {
        return [
            'date-interval' => [
                'startDate' => $this->startDate?->format('Y/m/d'),
                'endDate' => $this->endDate?->format('Y/m/d'),
            ],
            'time-interval' => [
                'startTime' => $this->startTime?->format('H:i:s'),
                'endTime' => $this->endTime?->format('H:i:s'),
            ],
            'timezone' => $this->timezone->getName(),
            'duration' => $this->duration?->format('P%yY%mM%dDT%hH%iM%sS'),
            'repeat-interval' => $this->repeatInterval->value,
            'repeat-frequency' => $this->repeatInterval->value,
            'repeat-count' => $this->repeatCount,
            'by-days' => $this->byDay ? array_map(static fn (DayOfWeek $d) => $d->value, $this->byDay) : null,
            'by-month-days' => $this->byMonthDay,
            'by-months' => $this->byMonth ? array_map(static fn (Month $d) => $d->value, $this->byMonth) : null,
            'by-month-weeks' => $this->byMonthWeek,
            'except-dates' => array_map(static fn(ChronosDate $chronosDate) => $chronosDate->format('Y/m/d'), $this->exceptDates)
        ];
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
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
                static fn (ChronosDate $chronosDate) => $chronosDate->format(DateTimeInterface::ATOM),
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
    public function withStartDate(ChronosDate $startDate): self
    {
        return new self(
            repeatInterval: $this->repeatInterval,
            startDate: $startDate,
            endDate: $this->endDate,
            startTime: $this->startTime,
            endTimeOrDuration: $this->endTime,
            repeatCount: $this->repeatCount,
            byDay: $this->byDay,
            byMonthDay: $this->byMonthDay,
            byMonth: $this->byMonth,
            byMonthWeek: $this->byMonthWeek,
            exceptDates: $this->exceptDates,
            timezone: $this->timezone->getName(),
        );
    }

    /** @throws ScheduleException */
    public function withEndDate(ChronosDate $endDate): self
    {
        return new self(
            repeatInterval: $this->repeatInterval,
            startDate: $this->startDate,
            endDate: $endDate,
            startTime: $this->startTime,
            endTimeOrDuration: $this->endTime,
            repeatCount: $this->repeatCount,
            byDay: $this->byDay,
            byMonthDay: $this->byMonthDay,
            byMonth: $this->byMonth,
            byMonthWeek: $this->byMonthWeek,
            exceptDates: $this->exceptDates,
            timezone: $this->timezone->getName(),
        );
    }

    /** @throws ScheduleException */
    private function validateStartAndEndDate(): void
    {
        if ($this->startDate && $this->endDate && $this->endDate->lessThan($this->startDate)) {
            throw new ScheduleException('startDate must be before or equal to endDate.');
        }
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
        foreach ($this->byDay as $dayOfWeek) {
            if ($dayOfWeek instanceof DayOfWeek) {
                continue;
            }

            throw new ScheduleException('Each "byDay" element must be a DayOfWeek enum instance.');
        }

        // check for duplicates
        if (count($this->byDay) <= 0) {
            return;
        }

        $byDayValues = array_map(static fn (DayOfWeek $dayOfWeek) => $dayOfWeek->value, $this->byDay);
        if (count(array_unique($byDayValues)) < count($byDayValues)) {
            throw new ScheduleException('byDay should not contain duplicates.');
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

        // check for duplicates
        if (count($this->byMonth) <= 0) {
            return;
        }

        $byMonthValues = array_map(static fn (Month $month) => $month->value, $this->byMonth);
        if (count(array_unique($byMonthValues)) < count($byMonthValues)) {
            throw new ScheduleException('byMonth should not contain duplicates.');
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
            if (! $exceptDate instanceof ChronosDate) {
                throw new ScheduleException('Each "exceptDates" element must be an instance of ChronosDate.');
            }
        }

        if (! $this->startDate || ! $this->endDate) {
            return;
        }

        foreach ($this->exceptDates as $exceptDate) {
            if ($exceptDate->lessThan($this->startDate) || $exceptDate->greaterThan($this->endDate)) {
                throw new ScheduleException('Some exceptDates are outside the range startDate/endDate.');
            }
        }
    }

    /** @throws ScheduleException */
    public static function fromJson(string $json): self
    {
        try {
            /** @var array<string,mixed> $data */
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ScheduleException('Invalid JSON: ' . $e->getMessage(), previous: $e);
        }

        try {
            $repeatFrequency = $data['repeatFrequency'] ?? null;
            if (! is_string($repeatFrequency)) {
                throw new ScheduleException('Missing or invalid "repeatFrequency".');
            }

            $repeatInterval = ScheduleInterval::from($repeatFrequency);

            $startDate = isset($data['startDate'])
                ? new ChronosDate((string) $data['startDate'])
                : null;

            $endDate = isset($data['endDate'])
                ? new ChronosDate((string) $data['endDate'])
                : null;

            $startTime = isset($data['startTime'])
                ? new ChronosTime((string) $data['startTime'])
                : null;

            $endTime = isset($data['endTime'])
                ? new ChronosTime((string) $data['endTime'])
                : null;

            $duration = isset($data['duration'])
                ? new DateInterval((string) $data['duration'])
                : null;

            $endTimeOrDuration = $endTime ?? $duration;

            $repeatCount = isset($data['repeatCount'])
                ? (int) $data['repeatCount']
                : null;

            $timezone = isset($data['timezone'])
                ? (string) $data['timezone']
                : null;

            $byDay = [];
            if (array_key_exists('byDay', $data) && $data['byDay'] !== null) {
                if (! is_array($data['byDay'])) {
                    throw new ScheduleException('"byDay" must be an array or null.');
                }

                foreach ($data['byDay'] as $val) {
                    $byDay[] = DayOfWeek::from((string) $val);
                }
            }

            $byMonthDay = [];
            if (array_key_exists('byMonthDay', $data) && $data['byMonthDay'] !== null) {
                if (! is_array($data['byMonthDay'])) {
                    throw new ScheduleException('"byMonthDay" must be an array or null.');
                }

                foreach ($data['byMonthDay'] as $val) {
                    $byMonthDay[] = (int) $val;
                }
            }

            $byMonth = [];
            if (array_key_exists('byMonth', $data) && $data['byMonth'] !== null) {
                if (! is_array($data['byMonth'])) {
                    throw new ScheduleException('"byMonth" must be an array or null.');
                }

                foreach ($data['byMonth'] as $val) {
                    $byMonth[] = Month::from((string) $val);
                }
            }

            $byMonthWeek = [];
            if (array_key_exists('byMonthWeek', $data) && $data['byMonthWeek'] !== null) {
                if (! is_array($data['byMonthWeek'])) {
                    throw new ScheduleException('"byMonthWeek" must be an array or null.');
                }

                foreach ($data['byMonthWeek'] as $val) {
                    $byMonthWeek[] = (int) $val;
                }
            }

            $exceptDates = [];
            if (array_key_exists('exceptDates', $data) && $data['exceptDates'] !== null) {
                if (! is_array($data['exceptDates'])) {
                    throw new ScheduleException('"exceptDates" must be an array or null.');
                }

                foreach ($data['exceptDates'] as $val) {
                    $exceptDates[] = new ChronosDate((string) $val);
                }
            }

            return new self(
                repeatInterval: $repeatInterval,
                startDate: $startDate,
                endDate: $endDate,
                startTime: $startTime,
                endTimeOrDuration: $endTimeOrDuration,
                repeatCount: $repeatCount,
                byDay: $byDay,
                byMonthDay: $byMonthDay,
                byMonth: $byMonth,
                byMonthWeek: $byMonthWeek,
                exceptDates: $exceptDates,
                timezone: $timezone,
            );
        } catch (Throwable $e) {
            if ($e instanceof ScheduleException) {
                throw $e;
            }

            throw new ScheduleException('Error creating Schedule from JSON: ' . $e->getMessage(), previous: $e);
        }
    }
}
