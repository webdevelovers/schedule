<?php

namespace WebDevelovers\Schedule\Tests;

use DateTimeInterface;
use RuntimeException;
use WebDevelovers\Schedule\Exception\ScheduleHumanizerException;
use WebDevelovers\Schedule\Holiday\HolidayProviderInterface;
use Yasumi\Exception\InvalidYearException;
use Yasumi\Exception\ProviderNotFoundException;
use Yasumi\Exception\UnknownLocaleException;
use Yasumi\Yasumi;

readonly class SampleYasumiProvider implements HolidayProviderInterface
{
    public function __construct(
        private string $country, // The country name. @see https://www.yasumi.dev/providers/providers.html
    ) {
    }

    /** @throws ScheduleHumanizerException */
    public function isHoliday(DateTimeInterface $date): bool
    {
        try {
            $yasumi = Yasumi::create($this->country, (int) $date->format('Y'));
        } catch (RuntimeException | InvalidYearException | UnknownLocaleException | ProviderNotFoundException $e) {
            throw new ScheduleHumanizerException($e->getMessage(), $e->getCode(), $e);
        }

        return $yasumi->isHoliday($date);
    }
}