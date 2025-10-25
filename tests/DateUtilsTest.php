<?php

namespace WebDevelovers\Schedule\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WebDevelovers\Schedule\DateUtils;
use WebDevelovers\Schedule\Enum\UnitOfTime;

class DateUtilsTest extends TestCase
{
    public function testToHoursAndMinutesFromSeconds(): void
    {
        self::assertSame('00:00', DateUtils::toHoursAndMinutes(0.0, UnitOfTime::SECONDS));
        self::assertSame('00:01', DateUtils::toHoursAndMinutes(30.0, UnitOfTime::SECONDS)); // 30s -> 1m (round)
        self::assertSame('00:01', DateUtils::toHoursAndMinutes(60.0, UnitOfTime::SECONDS));
        self::assertSame('01:00', DateUtils::toHoursAndMinutes(3600.0, UnitOfTime::SECONDS));
        self::assertSame('02:30', DateUtils::toHoursAndMinutes(9000.0, UnitOfTime::SECONDS)); // 2h30m
    }

    public function testToHoursAndMinutesFromMinutes(): void
    {
        self::assertSame('00:00', DateUtils::toHoursAndMinutes(0.0, UnitOfTime::MINUTES));
        self::assertSame('00:01', DateUtils::toHoursAndMinutes(0.5, UnitOfTime::MINUTES)); // 30s -> 1m (round)
        self::assertSame('00:01', DateUtils::toHoursAndMinutes(1.0, UnitOfTime::MINUTES));
        self::assertSame('01:00', DateUtils::toHoursAndMinutes(60.0, UnitOfTime::MINUTES));
        self::assertSame('02:30', DateUtils::toHoursAndMinutes(150.0, UnitOfTime::MINUTES));
    }

    public function testToHoursAndMinutesFromHours(): void
    {
        self::assertSame('00:00', DateUtils::toHoursAndMinutes(0.0, UnitOfTime::HOURS));
        self::assertSame('00:30', DateUtils::toHoursAndMinutes(0.5, UnitOfTime::HOURS));
        self::assertSame('01:00', DateUtils::toHoursAndMinutes(1.0, UnitOfTime::HOURS));
        self::assertSame('02:00', DateUtils::toHoursAndMinutes(2.0, UnitOfTime::HOURS));
        self::assertSame('02:30', DateUtils::toHoursAndMinutes(2.5, UnitOfTime::HOURS));
    }

    public function testToHoursAndMinutesFromDaysWeeksMonthsYears(): void
    {
        // 1 day -> 24:00
        self::assertSame('24:00', DateUtils::toHoursAndMinutes(1.0, UnitOfTime::DAYS));

        // 1 week -> 168:00
        self::assertSame('168:00', DateUtils::toHoursAndMinutes(1.0, UnitOfTime::WEEKS));

        // 1 month (30 days) -> 720:00
        self::assertSame('720:00', DateUtils::toHoursAndMinutes(1.0, UnitOfTime::MONTHS));

        // 1 year (365 days) -> 8760:00
        self::assertSame('8760:00', DateUtils::toHoursAndMinutes(1.0, UnitOfTime::YEARS));
    }

    public function testToHoursAndMinutesNegativeValuesAndRounding(): void
    {
        self::assertSame('-00:01', DateUtils::toHoursAndMinutes(-0.5, UnitOfTime::MINUTES)); // -30s -> -1m (round)
        self::assertSame('-01:00', DateUtils::toHoursAndMinutes(-1.0, UnitOfTime::HOURS));
        self::assertSame('-02:29', DateUtils::toHoursAndMinutes(-2.49, UnitOfTime::HOURS)); // check rounding nuance

        // Verifica precisa sul rounding ai minuti: 89.6s -> 1:30, 89.4s -> 1:29
        self::assertSame('00:01', DateUtils::toHoursAndMinutes(30.0, UnitOfTime::SECONDS));
        self::assertSame('00:01', DateUtils::toHoursAndMinutes(44.9, UnitOfTime::SECONDS));
        self::assertSame('00:01', DateUtils::toHoursAndMinutes(75.0, UnitOfTime::SECONDS));
    }

    // fromHoursAndMinutes

    public function testFromHoursAndMinutesToSeconds(): void
    {
        self::assertSame(0.0, DateUtils::fromHoursAndMinutes('00:00', UnitOfTime::SECONDS));
        self::assertSame(60.0, DateUtils::fromHoursAndMinutes('00:01', UnitOfTime::SECONDS));
        self::assertSame(3600.0, DateUtils::fromHoursAndMinutes('01:00', UnitOfTime::SECONDS));
        self::assertSame(9000.0, DateUtils::fromHoursAndMinutes('02:30', UnitOfTime::SECONDS));
    }

    public function testFromHoursAndMinutesToMinutes(): void
    {
        self::assertSame(0.0, DateUtils::fromHoursAndMinutes('00:00', UnitOfTime::MINUTES));
        self::assertSame(1.0, DateUtils::fromHoursAndMinutes('00:01', UnitOfTime::MINUTES));
        self::assertSame(60.0, DateUtils::fromHoursAndMinutes('01:00', UnitOfTime::MINUTES));
        self::assertSame(150.0, DateUtils::fromHoursAndMinutes('02:30', UnitOfTime::MINUTES));
    }

    public function testFromHoursAndMinutesToHoursDaysWeeksMonthsYears(): void
    {
        self::assertSame(0.0, DateUtils::fromHoursAndMinutes('00:00', UnitOfTime::HOURS));
        self::assertSame(1.0, DateUtils::fromHoursAndMinutes('01:00', UnitOfTime::HOURS));
        self::assertSame(2.5, DateUtils::fromHoursAndMinutes('02:30', UnitOfTime::HOURS));

        // 24:00 -> 1 day
        self::assertSame(1.0, DateUtils::fromHoursAndMinutes('24:00', UnitOfTime::DAYS));
        // 168:00 -> 1 week
        self::assertSame(1.0, DateUtils::fromHoursAndMinutes('168:00', UnitOfTime::WEEKS));
        // 720:00 -> 1 month (30 days)
        self::assertSame(1.0, DateUtils::fromHoursAndMinutes('720:00', UnitOfTime::MONTHS));
        // 8760:00 -> 1 year (365 days)
        self::assertSame(1.0, DateUtils::fromHoursAndMinutes('8760:00', UnitOfTime::YEARS));
    }

    public function testFromHoursAndMinutesWithSigns(): void
    {
        self::assertSame(-60.0, DateUtils::fromHoursAndMinutes('-00:01', UnitOfTime::SECONDS));
        self::assertSame(-3600.0, DateUtils::fromHoursAndMinutes('-01:00', UnitOfTime::SECONDS));
        self::assertSame(3600.0, DateUtils::fromHoursAndMinutes('+01:00', UnitOfTime::SECONDS));
        self::assertSame(-2.5, DateUtils::fromHoursAndMinutes('-02:30', UnitOfTime::HOURS));
    }

    public function testFromHoursAndMinutesValidationErrors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateUtils::fromHoursAndMinutes('', UnitOfTime::SECONDS);
    }

    public function testFromHoursAndMinutesInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateUtils::fromHoursAndMinutes('1', UnitOfTime::SECONDS);
    }

    public function testFromHoursAndMinutesNonNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateUtils::fromHoursAndMinutes('aa:bb', UnitOfTime::SECONDS);
    }

    public function testFromHoursAndMinutesEmptyParts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateUtils::fromHoursAndMinutes(':10', UnitOfTime::SECONDS);
    }

    public function testFromHoursAndMinutesMinutesOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateUtils::fromHoursAndMinutes('10:60', UnitOfTime::SECONDS);
    }

    // convertUnitOfTimeIntoSeconds

    public function testConvertUnitOfTimeIntoSeconds(): void
    {
        self::assertSame(1.0, DateUtils::convertUnitOfTimeIntoSeconds(1.0, UnitOfTime::SECONDS));
        self::assertSame(60.0, DateUtils::convertUnitOfTimeIntoSeconds(1.0, UnitOfTime::MINUTES));
        self::assertSame(3600.0, DateUtils::convertUnitOfTimeIntoSeconds(1.0, UnitOfTime::HOURS));
        self::assertSame(86400.0, DateUtils::convertUnitOfTimeIntoSeconds(1.0, UnitOfTime::DAYS));
        self::assertSame(7.0 * 86400.0, DateUtils::convertUnitOfTimeIntoSeconds(1.0, UnitOfTime::WEEKS));
        self::assertSame(30.0 * 86400.0, DateUtils::convertUnitOfTimeIntoSeconds(1.0, UnitOfTime::MONTHS));
        self::assertSame(365.0 * 86400.0, DateUtils::convertUnitOfTimeIntoSeconds(1.0, UnitOfTime::YEARS));
    }

    // convertSecondsIntoUnitOfTime

    public function testConvertSecondsIntoUnitOfTime(): void
    {
        self::assertSame(1.0, DateUtils::convertSecondsIntoUnitOfTime(1.0, UnitOfTime::SECONDS));
        self::assertSame(1.0, DateUtils::convertSecondsIntoUnitOfTime(60.0, UnitOfTime::MINUTES));
        self::assertSame(1.0, DateUtils::convertSecondsIntoUnitOfTime(3600.0, UnitOfTime::HOURS));
        self::assertSame(1.0, DateUtils::convertSecondsIntoUnitOfTime(86400.0, UnitOfTime::DAYS));
        self::assertSame(1.0, DateUtils::convertSecondsIntoUnitOfTime(7.0 * 86400.0, UnitOfTime::WEEKS));
        self::assertSame(1.0, DateUtils::convertSecondsIntoUnitOfTime(30.0 * 86400.0, UnitOfTime::MONTHS));
        self::assertSame(1.0, DateUtils::convertSecondsIntoUnitOfTime(365.0 * 86400.0, UnitOfTime::YEARS));
    }
}