<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule;

use Cake\Chronos\ChronosTime;
use DateInterval;
use DateMalformedIntervalStringException;
use InvalidArgumentException;

use function strlen;

class DateUtils
{
    /**
     * @return array{0: ChronosTime|null, 1: DateInterval|null}
     *
     * @throws InvalidArgumentException
     */
    public static function endTimeAndDurationFromParam(ChronosTime|DateInterval|string|null $endTimeOrDuration, ChronosTime|null $startTime): array
    {
        if ($endTimeOrDuration === null) {
            return [null, null];
        }

        if ($startTime === null) {
            throw new InvalidArgumentException('startTime is required when endTimeOrDuration is provided as not null (ChronosTime|DateInterval|string).');
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

            $startTimeAsDate = $startTime->toDateTimeImmutable();
            $endTime = new ChronosTime($startTimeAsDate->add($endTimeOrDuration));

            return [$endTime, $endTimeOrDuration];
        }

        if ($endTimeOrDuration instanceof ChronosTime) {
            $startTimeAsDate = $startTime->toDateTimeImmutable();
            $endTimeAsDate = $endTimeOrDuration->toDateTimeImmutable();
            if ($endTimeOrDuration->lessThan($startTime)) {
                $endTimeAsDate = $endTimeAsDate->add(new DateInterval('P1D'));
            }

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
