<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Holiday;

use Cake\Chronos\ChronosDate;

interface HolidayProviderInterface
{
    public function isHoliday(ChronosDate $date): bool;
}
