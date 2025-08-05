<?php

namespace WebDevelovers\Schedule\Tests;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Exception\ScheduleException;
use WebDevelovers\Schedule\Schedule;

class ScheduleTest extends TestCase
{
    public function testSimpleConstruction()
    {
        $schedule = new Schedule(ScheduleInterval::DAILY);
        $this->assertEquals(ScheduleInterval::DAILY, $schedule->repeatInterval);
    }

    /** @throws ScheduleException */
    public function testValidScheduleDoesNotThrow()
    {
        new Schedule(
            repeatInterval: ScheduleInterval::EVERY_WEEK,
            startDate: new ChronosDate('2024-07-10'),
            endDate: new ChronosDate('2024-08-10'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: new ChronosTime('10:00'),
            repeatCount: 2,
            byDay: [DayOfWeek::MONDAY, DayOfWeek::TUESDAY],
            byMonthDay: [1, 15],
            byMonth: [Month::AUGUST],
            byMonthWeek: [-1],
            exceptDates: [new \DateTimeImmutable('2024-07-15')],
            timezone: 'Europe/Rome'
        );

        $this->expectNotToPerformAssertions();
    }

    /** @throws ScheduleException */
    public function testEndDateBeforeStartDate()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-07-10'),
            endDate: new ChronosDate('2024-07-09'),
        );
    }

    /** @throws ScheduleException */
    public function testDurationIsCalculatedWithStartTimeEndTimeAsChronosTime()
    {
        $startTime = new ChronosTime('09:00');
        $endTime = new ChronosTime('11:30');

        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: null,
            endDate: null,
            startTime: $startTime,
            endTimeOrDuration: $endTime
        );

        $this->assertEquals('PT02H30M00S', $schedule->duration->format('PT%HH%IM%SS'));;
    }

    /** @throws ScheduleException */
    public function testDurationIsCalculatedWithStartTimeEndTimeAsInterval()
    {
        $startTime = new ChronosTime('09:00');
        $endTime = new \DateInterval('PT2H30M');

        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: null,
            endDate: null,
            startTime: $startTime,
            endTimeOrDuration: $endTime
        );

        $this->assertEquals('PT02H30M00S', $schedule->duration->format('PT%HH%IM%SS'));;
    }

    /** @throws ScheduleException */
    public function testDurationIsCalculatedWithStartTimeEndTimeAsString()
    {
        $startTime = new ChronosTime('09:00');
        $endTime = 'PT2H30M';

        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: null,
            endDate: null,
            startTime: $startTime,
            endTimeOrDuration: $endTime
        );

        $this->assertEquals('PT02H30M00S', $schedule->duration->format('PT%HH%IM%SS'));;
    }

    /** @throws ScheduleException */
    public function testDurationIsNotCalculatedWithoutEndTime()
    {
        $startTime = new ChronosTime('09:00');

        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: null,
            endDate: null,
            startTime: $startTime,
        );

        $this->assertEquals(null, $schedule->duration);;
    }

    public function testRepeatCountZero()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            repeatCount: 0
        );
    }

    public function testRepeatCountNegative()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            repeatCount: -5
        );
    }

    public function testInvalidDurationString()
    {
        $this->expectException(ScheduleException::class);
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            endTimeOrDuration: 'XXX',
        );
        $schedule->validate();
    }

    public function testZeroDuration()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            ScheduleInterval::DAILY,
            endTimeOrDuration: 'PT0S'
        );
    }

    public function testByDayDuplicate()
    {
        $this->expectException(ScheduleException::class);
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byDay: [DayOfWeek::MONDAY, DayOfWeek::MONDAY]
        );
        $schedule->validate();
    }

    public function testByDayInvalidType()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byDay: [1,2]
        );
    }

    public function testByMonthInvalidType()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonth: [1,2]
        );
    }

    public function testByMonthDayOutOfRange()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthDay: [32]
        );
    }

    public function testZeroByMonthDay()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthDay: [0]
        );
    }

    public function testByMonthWeekOutOfRange()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthWeek: [7]
        );
    }

    public function testByMonthWeekNegativeOutOfRange()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthWeek: [-7]
        );
    }

    public function testByMonthWeekZero()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthWeek: [0]
        );
    }

    public function testInvalidExceptDateType()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            exceptDates: [1],
        );
    }

    /** @throws ScheduleException */
    public function testAsArraySerialization()
    {
        $start = new ChronosDate('2024-08-01');
        $end = new ChronosDate('2024-08-31');
        $startTime = new ChronosTime('09:00');
        $endTime = new ChronosTime('11:00');
        $except = [
            new DateTimeImmutable('2024-08-15'),
            new DateTimeImmutable('2024-08-18T09:00:00+02:00')
        ];

        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: $start,
            endDate: $end,
            startTime: $startTime,
            endTimeOrDuration: $endTime,
            repeatCount: 10,
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
        $this->assertSame('P0Y0M0DT2H0M0S', $array['duration']);
        $this->assertSame(ScheduleInterval::DAILY->value, $array['repeatFrequency']);
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