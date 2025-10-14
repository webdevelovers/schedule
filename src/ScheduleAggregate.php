<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use Cake\Chronos\ChronosDate;
use DateTimeInterface;
use JsonException;
use JsonSerializable;
use Throwable;
use WebDevelovers\Schedule\Exception\ScheduleException;

use function array_map;
use function array_push;
use function array_values;
use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;

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

        return [
            'type' => 'ScheduleAggregate',
            'schedules' => array_map(static fn (Schedule $s) => $s->toArray(), $this->schedules),
            'bounds' => [
                'startDate' => $minStart?->format(DateTimeInterface::ATOM),
                'endDate' => $maxEnd?->format(DateTimeInterface::ATOM),
            ],
        ];
    }

    /**
     * Creates a ScheduleAggregate from an array representation
     *
     * @param array<string,mixed> $data
     * @throws ScheduleException
     */
    public static function fromArray(array $data): self
    {
        try {
            if (! isset($data['schedules']) || ! is_array($data['schedules'])) {
                throw new ScheduleException('Missing "schedules" array.');
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

    public function __serialize(): array
    {
        return $this->toArray();
    }

    /** @throws ScheduleException */
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

    public function getSchedules(): array
    {
        return $this->schedules;
    }
}
