<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Holiday;

use DateTimeInterface;

interface HolidayProviderInterface
{
    public function isHoliday(DateTimeInterface $date): bool;
}
