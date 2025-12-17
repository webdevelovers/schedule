<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use DateInterval;
use DateInvalidTimeZoneException;
use DateMalformedIntervalStringException;
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
use function hash;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function strlen;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

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

    public readonly string $identifier;
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
     * The time when each occurrence ends or the duration of the occurrences as DateInterval or string.
     * @param int|null $repeatCount The maximum number of requested occurrences.
     * @param DayOfWeek[] $byDay Filters by days of the week (e.g., only Mondays and Wednesdays).
     * @param int[] $byMonthDay Filters by days of the month (e.g., 1st, 15th).
     * @param Month[] $byMonth Filters by months of the year (e.g., January, June).
     * @param int[] $byMonthWeek Filters by weeks within the month (e.g., 1 = first week, -1 = last week).
     * @param ChronosDate[] $exceptDates Array of excluded dates or datetimes. Should be inside the startDate and endDate range.
     * @param ChronosDate[] $includeDates Array of explicitly included dates (extra occurrences). Should be inside the startDate and endDate range.
     * @param string|null $timezone The timezone (IANA standard) for all occurrences.
     *
     * @throws ScheduleException
     */
    public function __construct(
        public ScheduleInterval $repeatInterval = ScheduleInterval::NONE,
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
        public array $includeDates = [],
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
        $this->validateIncludeDates();

        $this->identifier = $this->generateIdentifier();
    }

    /** @throws ScheduleException */
    private function generateIdentifier(): string
    {
        $dataArray = [
            'byDay' => $this->byDay ? array_map(static fn (DayOfWeek $d) => $d->value, $this->byDay) : null,
            'byMonth' => $this->byMonth ? array_map(static fn (Month $d) => $d->value, $this->byMonth) : null,
            'byMonthDay' => $this->byMonthDay,
            'byMonthWeek' => $this->byMonthWeek,
            'duration' => $this->duration?->format('P%yY%mM%dDT%hH%iM%sS'),
            'endDate' => $this->endDate?->format('Y/m/d'),
            'endTime' => $this->endTime?->format('H:i:s'),
            'exceptDates' => array_map(static fn (ChronosDate $chronosDate) => $chronosDate->format('Y/m/d'), $this->exceptDates),
            'includeDates' => array_map(static fn (ChronosDate $chronosDate) => $chronosDate->format('Y/m/d'), $this->includeDates),
            'repeatCount' => $this->repeatCount,
            'repeatInterval' => $this->repeatInterval->value,
            'startDate' => $this->startDate?->format('Y/m/d'),
            'startTime' => $this->startTime?->format('H:i:s'),
            'timezone' => $this->timezone->getName(),
        ];

        try {
            $json = json_encode($dataArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ScheduleException('Error generating Identifier: ' . $e->getMessage(), previous: $e);
        }

        return hash('sha256', $json);
    }

    /**
     * Converts the Schedule to an array representation
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'startDate' => $this->startDate?->format('Y/m/d'),
            'endDate' => $this->endDate?->format('Y/m/d'),
            'startTime' => $this->startTime?->format('H:i:s'),
            'endTime' => $this->endTime?->format('H:i:s'),
            'timezone' => $this->timezone->getName(),
            'duration' => $this->duration?->format('P%yY%mM%dDT%hH%iM%sS'),
            'repeatInterval' => $this->repeatInterval->value,
            'repeatCount' => $this->repeatCount,
            'byDay' => $this->byDay ? array_map(static fn (DayOfWeek $d) => $d->value, $this->byDay) : null,
            'byMonthDay' => $this->byMonthDay,
            'byMonth' => $this->byMonth ? array_map(static fn (Month $d) => $d->value, $this->byMonth) : null,
            'byMonthWeek' => $this->byMonthWeek,
            'exceptDates' => array_map(static fn (ChronosDate $chronosDate) => $chronosDate->format('Y/m/d'), $this->exceptDates),
            'includeDates' => array_map(static fn (ChronosDate $chronosDate) => $chronosDate->format('Y/m/d'), $this->includeDates),
        ];
    }

    /**
     * Creates a Schedule instance from an array representation
     *
     * @param array<string,mixed> $data
     *
     * @throws ScheduleException
     */
    public static function fromArray(array $data): self
    {
        try {
            $repeatIntervalString = $data['repeatInterval'] ?? null;
            if (! is_string($repeatIntervalString)) {
                throw new ScheduleException('Missing or invalid "repeatInterval".');
            }

            $repeatInterval = ScheduleInterval::from($repeatIntervalString);

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

            $includeDates = [];
            if (array_key_exists('includeDates', $data) && $data['includeDates'] !== null) {
                if (! is_array($data['includeDates'])) {
                    throw new ScheduleException('"includeDates" must be an array or null.');
                }

                foreach ($data['includeDates'] as $val) {
                    $includeDates[] = new ChronosDate((string) $val);
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
                includeDates: $includeDates,
                timezone: $timezone,
            );
        } catch (Throwable $e) {
            if ($e instanceof ScheduleException) {
                throw $e;
            }

            throw new ScheduleException('Error creating Schedule from array: ' . $e->getMessage(), previous: $e);
        }
    }

    /** @return array<string,mixed> */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<string,mixed> $data
     *
     * @throws ScheduleException
     */
    public function __unserialize(array $data): void
    {
        $instance = self::fromArray($data);

        $this->repeatInterval = $instance->repeatInterval;
        $this->startDate = $instance->startDate;
        $this->endDate = $instance->endDate;
        $this->startTime = $instance->startTime;
        $this->endTime = $instance->endTime;
        $this->duration = $instance->duration;
        $this->timezone = $instance->timezone;
        $this->repeatCount = $instance->repeatCount;
        $this->byDay = $instance->byDay;
        $this->byMonthDay = $instance->byMonthDay;
        $this->byMonth = $instance->byMonth;
        $this->byMonthWeek = $instance->byMonthWeek;
        $this->exceptDates = $instance->exceptDates;
        $this->includeDates = $instance->includeDates;
        $this->identifier = $this->generateIdentifier();
    }

    /**
     * Converts to JSON string
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR);
    }

    /**
     * Creates a Schedule from JSON string
     *
     * @throws ScheduleException
     */
    public static function fromJson(string $json): self
    {
        try {
            /** @var array<string,mixed> $data */
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ScheduleException('Invalid JSON: ' . $e->getMessage(), previous: $e);
        }

        return self::fromArray($data);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @throws ScheduleException */
    private function initEndTimeAndDuration(
        ChronosTime|DateInterval|string|null $endTimeOrDuration,
    ): void {
        try {
            [$this->endTime, $this->duration] = self::endTimeAndDurationFromParam($endTimeOrDuration, $this->startTime);
        } catch (InvalidArgumentException $e) {
            throw new ScheduleException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function isRecurring(): bool
    {
        return $this->repeatInterval !== ScheduleInterval::NONE;
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
            includeDates: $this->includeDates,
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
            includeDates: $this->includeDates,
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
    private function validateIncludeDates(): void
    {
        foreach ($this->includeDates as $includeDate) {
            if (! $includeDate instanceof ChronosDate) {
                throw new ScheduleException('Each "includeDates" element must be an instance of ChronosDate.');
            }
        }

        // check for duplicates
        if (count($this->includeDates) > 0) {
            $values = array_map(static fn (ChronosDate $d) => $d->format('Y-m-d'), $this->includeDates);
            if (count(array_unique($values)) < count($values)) {
                throw new ScheduleException('includeDates should not contain duplicates.');
            }
        }

        if (! $this->startDate || ! $this->endDate) {
            return;
        }

        foreach ($this->includeDates as $includeDate) {
            if ($includeDate->lessThan($this->startDate) || $includeDate->greaterThan($this->endDate)) {
                throw new ScheduleException('Some includeDates are outside the range startDate/endDate.');
            }
        }
    }

    /**
     * @return array{0: ChronosTime|null, 1: DateInterval|null}
     *
     * @throws InvalidArgumentException
     */
    private static function endTimeAndDurationFromParam(ChronosTime|DateInterval|string|null $endTimeOrDuration, ChronosTime|null $startTime): array
    {
        if ($endTimeOrDuration === null) {
            return [null, null];
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
                throw new InvalidArgumentException('Duration interval cannot be zero.');
            }

            if ($startTime === null) {
                throw new InvalidArgumentException('startTime is required when endTimeOrDuration is provided as DateInterval.');
            }

            $startTimeAsDate = $startTime->toDateTimeImmutable();
            $endTime = new ChronosTime($startTimeAsDate->add($endTimeOrDuration));

            return [$endTime, $endTimeOrDuration];
        }

        if ($endTimeOrDuration instanceof ChronosTime) {
            $endTimeAsDate = $endTimeOrDuration->toDateTimeImmutable();
            if ($startTime === null) {
                return [$endTimeOrDuration, null];
            }

            if ($endTimeOrDuration->lessThan($startTime)) {
                $endTimeAsDate = $endTimeAsDate->add(new DateInterval('P1D'));
            }

            $startTimeAsDate = $startTime->toDateTimeImmutable();
            $duration = $startTimeAsDate->diff($endTimeAsDate);

            return [$endTimeOrDuration, $duration];
        }

        if (strlen($endTimeOrDuration) > 0) {
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
                    throw new InvalidArgumentException('Duration interval cannot be zero.');
                }

                if ($startTime === null) {
                    throw new InvalidArgumentException('startTime is required when endTimeOrDuration is provided as a DateInterval string.');
                }

                $startTimeAsDate = $startTime->toDateTimeImmutable();
                $endTimeAsDate = $startTimeAsDate->add($duration);
                $endTime = new ChronosTime($endTimeAsDate);

                return [$endTime, $duration];
            } catch (DateMalformedIntervalStringException $e) {
                throw new InvalidArgumentException('Duration as a string should be in ISO8601 format: ' . $endTimeOrDuration, 0, $e);
            }
        }

        throw new InvalidArgumentException('Unsupported endTimeOrDuration type.');
    }
}
