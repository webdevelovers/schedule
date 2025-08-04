<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Exception;

use InvalidArgumentException;

use function implode;

/** Exception thrown when a Schedule is constructed with an invalid configuration. */
class ScheduleValidationException extends InvalidArgumentException
{
    /** @param string[] $errors */
    public function __construct(public array $errors)
    {
        parent::__construct("Schedule validation failed:\n" . implode("\n", $errors));
    }
}
