<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use JsonSerializable;
use Throwable;
use WebDevelovers\Schedule\Exception\ScheduleException;

use function array_key_exists;
use function array_map;
use function array_push;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;
use function usort;

use const JSON_THROW_ON_ERROR;

class ScheduleAggregate implements JsonSerializable
{
    /** @var Schedule[] */
    private array $schedules;

    /**
     * @param Schedule[] $schedules
     *
     * @throws ScheduleException
     */
    public function __construct(array $schedules = [])
    {
        $this->assertSchedules($schedules);
        $this->schedules = array_values($schedules);
    }

    /**
     * Converts the aggregate to an array representation
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        [$minStart, $maxEnd] = $this->getBounds();
        $utc = new DateTimeZone('UTC');

        return [
            'type' => 'ScheduleAggregate',
            'schedules' => array_map(static fn (Schedule $s) => $s->toArray(), $this->schedules),
            'bounds' => [
                'startDate' => $minStart ? $minStart->toDateTimeImmutable($utc)->format(DateTimeInterface::ATOM) : null,
                'endDate' => $maxEnd ? $maxEnd->toDateTimeImmutable($utc)->format(DateTimeInterface::ATOM) : null,
            ],
        ];
    }

    /**
     * Creates a ScheduleAggregate from an array representation
     *
     * @param array<string,mixed> $data
     *
     * @throws ScheduleException
     */
    public static function fromArray(array $data): self
    {
        if (count($data) === 0) {
            return new self([]);
        }

        try {
            if (
                ! array_key_exists('schedules', $data) ||
                ! is_array($data['schedules'])
            ) {
                throw new ScheduleException('Missing or invalid "schedules" key.');
            }

            $schedules = [];
            foreach ($data['schedules'] as $idx => $scheduleData) {
                if (! is_array($scheduleData)) {
                    throw new ScheduleException('Each element of "schedules" must be an array.');
                }

                $schedules[] = Schedule::fromArray($scheduleData);
            }

            return new self($schedules);
        } catch (Throwable $e) {
            if ($e instanceof ScheduleException) {
                throw $e;
            }

            throw new ScheduleException('Error creating ScheduleAggregate from array: ' . $e->getMessage(), previous: $e);
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
        $this->schedules = $instance->schedules;
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
     * Creates a ScheduleAggregate from JSON string
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

    /** @return Schedule[] */
    public function all(): array
    {
        return $this->schedules;
    }

    /** @throws ScheduleException */
    public function withAdded(Schedule $schedule): self
    {
        $copy = $this->schedules;
        $copy[] = $schedule;

        return new self($copy);
    }

    /**
     * @param Schedule[] $schedules
     *
     * @throws ScheduleException
     */
    public function withSchedules(array $schedules): self
    {
        return new self($schedules);
    }

    /** @return array{ ChronosDate|null, ChronosDate|null } */
    public function getBounds(): array
    {
        $minStart = null;
        $maxEnd   = null;

        foreach ($this->schedules as $s) {
            if ($s->startDate !== null) {
                $minStart = $minStart === null || $s->startDate->lessThan($minStart) ? $s->startDate : $minStart;
            }

            if ($s->endDate === null) {
                continue;
            }

            $maxEnd = $maxEnd === null || $s->endDate->greaterThan($maxEnd) ? $s->endDate : $maxEnd;
        }

        return [$minStart, $maxEnd]; // [ChronosDate|null, ChronosDate|null]
    }

    public function getMinStartDate(): ChronosDate|null
    {
        [$minStart] = $this->getBounds();

        return $minStart;
    }

    public function getMaxEndDate(): ChronosDate|null
    {
        [$minStart, $maxEnd] = $this->getBounds();

        return $maxEnd;
    }

    /**
     * Merge two ScheduleAggregate
     *
     * @throws ScheduleException
     */
    public function merge(ScheduleAggregate ...$otherSchedules): self
    {
        $merged = $this->schedules;
        foreach ($otherSchedules as $agg) {
            array_push($merged, ...$agg->schedules);
        }

        return new self($merged);
    }

    /**
     * Filter schedules that overlap with the given range
     *
     * @throws ScheduleException
     */
    public function intersecting(ChronosDate $from, ChronosDate $to): self
    {
        if ($to->lessThan($from)) {
            throw new ScheduleException('Invalid range: $to must be >= $from.');
        }

        $filtered = [];
        foreach ($this->schedules as $s) {
            $sStart = $s->startDate ?? $from;
            $sEnd   = $s->endDate ?? $to;

            $overlaps = ! ($sEnd->lessThan($from) || $sStart->greaterThan($to));
            if (! $overlaps) {
                continue;
            }

            $filtered[] = $s;
        }

        return new self($filtered);
    }

    /**
     * @param Schedule[] $schedules
     *
     * @throws ScheduleException
     */
    private function assertSchedules(array $schedules): void
    {
        foreach ($schedules as $i => $s) {
            if (! $s instanceof Schedule) {
                throw new ScheduleException(sprintf('Element at index %d is not a Schedule instance.', $i));
            }
        }
    }

    /** @return Schedule[] */
    public function getSchedules(): array
    {
        return $this->schedules;
    }

    /** @throws ScheduleException */
    public function sortedBy(string $field, bool $ascending = true): self
    {
        if (! in_array($field, ['startDate', 'endDate', 'startTime', 'endTime'], true)) {
            throw new ScheduleException(sprintf('Invalid sort field: %s', $field));
        }

        $sorted = $this->schedules;

        usort($sorted, static function (Schedule $a, Schedule $b) use ($field, $ascending): int {
            $valueA = match ($field) {
                'startDate' => $a->startDate,
                'endDate' => $a->endDate,
                'startTime' => $a->startTime,
                'endTime' => $a->endTime,
            };

            $valueB = match ($field) {
                'startDate' => $b->startDate,
                'endDate' => $b->endDate,
                'startTime' => $b->startTime,
                'endTime' => $b->endTime,
            };

            // Handle null values - nulls go to the end
            if ($valueA === null && $valueB === null) {
                return 0;
            }

            if ($valueA === null) {
                return $ascending ? 1 : -1;
            }

            if ($valueB === null) {
                return $ascending ? -1 : 1;
            }

            // Compare dates/times
            if ($valueA instanceof ChronosDate && $valueB instanceof ChronosDate) {
                $comparison = $valueA->lessThan($valueB) ? -1 : ($valueA->greaterThan($valueB) ? 1 : 0);
            } elseif ($valueA instanceof ChronosTime && $valueB instanceof ChronosTime) {
                $comparison = $valueA->lessThan($valueB) ? -1 : ($valueA->greaterThan($valueB) ? 1 : 0);
            } else {
                $comparison = 0;
            }

            return $ascending ? $comparison : -$comparison;
        });

        return new self($sorted);
    }
}
