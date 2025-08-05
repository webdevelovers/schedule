<?php

namespace WebDevelovers\Schedule\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Frequency;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Exception\ScheduleException;
use WebDevelovers\Schedule\Exception\ScheduleValidationException;
use WebDevelovers\Schedule\Schedule;

class ScheduleTest extends TestCase
{
    public function testSimpleConstruction()
    {
        $schedule = new Schedule(Frequency::DAILY);
        $this->assertEquals(Frequency::DAILY, $schedule->repeatFrequency);
    }

    public function testValidScheduleDoesNotThrow()
    {
        $schedule = new Schedule(
            Frequency::WEEKLY,
            new DateTimeImmutable('2024-07-10'),
            new DateTimeImmutable('2024-08-10'),
            new DateTimeImmutable('2024-07-10 09:00'),
            new DateTimeImmutable('2024-07-10 10:00'),
            2,
            null,
            [DayOfWeek::MONDAY, DayOfWeek::TUESDAY],
            [1, 15],
            [],
            [-1]
        );
        $this->expectNotToPerformAssertions();
        $schedule->validate();
    }

    public function testStartDateBeforeEndDate()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            new DateTimeImmutable('2024-07-10'),
            new DateTimeImmutable('2024-07-09')
        );
        $schedule->validate();
    }

    public function testStartTimeBeforeEndTime()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            new DateTimeImmutable('2024-07-10 15:00'),
            new DateTimeImmutable('2024-07-10 14:00')
        );
        $schedule->validate();
    }

    public function testDurationIsCalculatedFromStartAndEndTime()
    {
        $startTime = new \DateTimeImmutable('2024-07-10 09:00');
        $endTime = new \DateTimeImmutable('2024-07-10 11:30');
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            $startTime,
            $endTime
        );

        // Deve calcolare automaticamente la durata: 2 ore e 30 minuti
        $this->assertEquals('PT2H30M0S', $schedule->duration);
    }

    public function testEndTimeIsCalculatedFromStartTimeAndDuration()
    {
        $startTime = new \DateTimeImmutable('2024-07-10 09:00');
        $duration = 'PT1H45M';
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            $startTime,
            null,
            null,
            $duration
        );

        // Deve calcolare automaticamente endTime = 10:45
        $this->assertInstanceOf(\DateTimeImmutable::class, $schedule->endTime);
        $this->assertEquals('2024-07-10 10:45', $schedule->endTime->format('Y-m-d H:i'));
    }

    public function testRepeatCountNegative()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            null,
            null,
            0 // Not valid: must be >= 1
        );
        $schedule->validate();
    }

    public function testInvalidDurationString()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            null,
            null,
            null,
            'XXX' // Not a valid ISO8601 interval
        );
        $schedule->validate();
    }

    public function testZeroDuration()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            null,
            null,
            null,
            'PT0S'
        );
        $schedule->validate();
    }

    public function testByDayDuplicate()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            null,
            null,
            null,
            null,
            [DayOfWeek::MONDAY, DayOfWeek::MONDAY]
        );
        $schedule->validate();
    }

    /** @throws ScheduleException */
    public function testByDayInvalidType()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            null,
            null,
            null,
            null,
            [1, 2]
        );
        $schedule->validate();
    }

    /** @throws ScheduleException */
    public function testByMonthInvalidType()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            null,
            null,
            null,
            null,
            [],
            [],
            [1, 2]
        );
        $schedule->validate();
    }

    /** @throws ScheduleException */
    public function testByMonthDayOutOfRange()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            null,
            null,
            null,
            null,
            [],
            [32]
        );
        $schedule->validate();
    }

    /** @throws ScheduleException */
    public function testByMonthWeekOutOfRange()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            null,
            null,
            null,
            null,
            [],
            [],
            [],
            [7]
        );
        $schedule->validate();
    }

    /** @throws ScheduleException */
    public function testInvalidExceptDateType()
    {
        $this->expectException(ScheduleValidationException::class);
        $schedule = new Schedule(
            Frequency::DAILY,
            null,
            null,
            null,
            null,
            null,
            null,
            [],
            [],
            [],
            [],
            [42] // not a DateTimeInterface
        );
        $schedule->validate();
    }

    /** @throws ScheduleException */
    public function testAsArraySerialization()
    {
        $start = new DateTimeImmutable('2024-08-01');
        $end = new DateTimeImmutable('2024-08-31');
        $startTime = (new DateTimeImmutable('2024-08-01 09:00'))->setDate(2000, 1, 1); // Solo orario per startTime
        $endTime = (new DateTimeImmutable('2024-08-01 11:00'))->setDate(2000, 1, 1);   // Solo orario per endTime
        $except = [new DateTimeImmutable('2024-08-15'), new DateTimeImmutable('2024-08-18T09:00:00+02:00')];

        $schedule = new Schedule(
            repeatFrequency: Frequency::DAILY,
            startDate: $start,
            endDate: $end,
            startTime: $startTime,
            endTime: $endTime,
            repeatCount: 10,
            duration: 'PT2H',
            byDay: [DayOfWeek::MONDAY],
            byMonthDay: [1, 15],
            byMonth: [Month::AUGUST],
            byMonthWeek: [1],
            exceptDates: $except,
            timezone: 'Europe/Rome'
        );

        $array = $schedule->asArray();

        $this->assertArrayHasKey('startDate', $array);
        $this->assertSame('2024-08-01T00:00:00+00:00', $array['startDate']);
        $this->assertSame('2024-08-31T00:00:00+00:00', $array['endDate']);
        $this->assertSame('09:00:00', $array['startTime']);
        $this->assertSame('11:00:00', $array['endTime']);
        $this->assertSame('PT2H', $array['duration']);
        $this->assertSame(Frequency::DAILY->value, $array['repeatFrequency']);
        $this->assertSame(10, $array['repeatCount']);
        $this->assertSame('Europe/Rome', $array['timezone']);
        $this->assertEquals([DayOfWeek::MONDAY->value], $array['byDay']);
        $this->assertEquals([1, 15], $array['byMonthDay']);
        $this->assertEquals([Month::AUGUST->value], $array['byMonth']);
        $this->assertEquals([1], $array['byMonthWeek']);
        $this->assertEquals([
            $except[0]->format(DATE_ATOM),
            $except[1]->format(DATE_ATOM)
        ], $array['exceptDates']);
    }
}