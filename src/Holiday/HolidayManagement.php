<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Holiday;

//TODO: for future implementations
enum HolidayManagement: string
{
    case INCLUDE = 'include';
    case SKIP_TO_NEXT_INTERVAL = 'skip_to_next_interval';
    case SKIP_TO_NEXT_AVAILABLE_DAY = 'skip_to_next_available_day';
    case SKIP_TO_NEAREST_AVAILABLE_DAY = 'skip_to_nearest_available_day';
    case POSTPONE_ALL_INTERVALS_TO_NEXT_AVAILABLE_DAY = 'postpone_all_intervals_to_next_available_day';
}