<?php

namespace WebDevelovers\Schedule\Tests;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
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
            exceptDates: [new ChronosDate('2024-07-15')],
            timezone: 'Europe/Rome'
        );

        $this->expectNotToPerformAssertions();
    }

    public function testInvalidTimezoneThrows()
    {
        $this->expectException(ScheduleException::class);

        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            timezone: 'Invalid/Zone'
        );
    }

    public function testDefaultTimezoneIsSetIfNotProvided()
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY
        );

        $this->assertSame(date_default_timezone_get(), $schedule->timezone->getName());
    }

    public function testIsRecurringTrueFalse()
    {
        $recurring = new Schedule(ScheduleInterval::DAILY);
        $notRecurring = new Schedule(ScheduleInterval::NONE);

        $this->assertTrue($recurring->isRecurring());
        $this->assertFalse($notRecurring->isRecurring());
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


    public function testInvalidDurationString()
    {
        $this->expectException(ScheduleException::class);
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            endTimeOrDuration: 'XXX',
        );
    }

    public function testZeroDuration()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            ScheduleInterval::DAILY,
            endTimeOrDuration: 'PT0S'
        );
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

    public function testByDayInvalidType()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byDay: [1,2]
        );
    }

    public function testByDayDuplicate()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byDay: [DayOfWeek::MONDAY, DayOfWeek::MONDAY]
        );
    }

    /** @throws ScheduleException */
    public function testByMonthValidMultipleDoesNotThrow()
    {
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonth: [Month::JANUARY, Month::MARCH, Month::DECEMBER]
        );

        $this->expectNotToPerformAssertions();
    }

    public function testByMonthInvalidType()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonth: [1,3]
        );
    }

    public function testByMonthDuplicateShouldThrow()
    {
        $this->expectException(ScheduleException::class);

        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonth: [Month::MARCH, Month::MARCH]
        );
    }

    public function testByMonthDayInvalidType()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthDay: ['0']
        );
    }

    public function testByMonthDayZero()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthDay: [0]
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

    public function testByMonthWeekInvalidType()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthWeek: ['0']
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

    /** @throws ScheduleException */
    public function testByMonthWeekBoundaryValuesAccepted()
    {
        // Limiti ammessi: 6 e -6
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthWeek: [6]
        );

        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthWeek: [-6]
        );

        $this->expectNotToPerformAssertions();
    }

    /** @throws ScheduleException */
    public function testByMonthWeekMultipleValuesAccepted()
    {
        // Combinazione di settimane: prima e ultima del mese
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            byMonthWeek: [1, -1]
        );

        $this->expectNotToPerformAssertions();
    }

    public function testInvalidExceptDateType()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            exceptDates: [1],
        );
    }

    public function testExceptDateOutsideStartEndRangeThrows()
    {
        $this->expectException(ScheduleException::class);

        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-08-01'),
            endDate: new ChronosDate('2024-08-31'),
            exceptDates: [new ChronosDate('2024-09-01')]
        );
    }

    /** @throws ScheduleException */
    public function testDoesNotThrowWithExceptDatesOnBoundaries()
    {
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-08-01'),
            endDate: new ChronosDate('2024-08-31'),
            exceptDates: [
                new ChronosDate('2024-08-01'),
                new ChronosDate('2024-08-31'),
            ]
        );

        $this->expectNotToPerformAssertions();
    }

    /** @throws ScheduleException */
    public function testExceptDatesWithOnlyStartDateDoesNotValidateRange()
    {
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-08-10'),
            exceptDates: [
                new ChronosDate('2024-08-01'),
            ]
        );

        $this->expectNotToPerformAssertions();
    }

    /** @throws ScheduleException */
    public function testExceptDatesWithOnlyEndDateDoesNotValidateRange()
    {
        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            endDate: new ChronosDate('2024-08-10'),
            exceptDates: [
                new ChronosDate('2024-08-20'),
            ]
        );

        $this->expectNotToPerformAssertions();
    }

    public function testExceptDatesMixedOneOutsideThrows()
    {
        $this->expectException(ScheduleException::class);

        new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-08-01'),
            endDate: new ChronosDate('2024-08-31'),
            exceptDates: [
                new ChronosDate('2024-08-10'),
                new ChronosDate('2024-09-01'),
            ]
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
            new ChronosDate('2024-08-15'),
            new ChronosDate('2024-08-18')
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

        $array = $schedule->jsonSerialize();

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

    public function testAsArrayWithOptionalFieldsEmptyAndDefaultTimezone()
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY
        );

        $array = $schedule->jsonSerialize();

        $this->assertNull($array['startDate']);
        $this->assertNull($array['endDate']);
        $this->assertNull($array['startTime']);
        $this->assertNull($array['endTime']);
        $this->assertNull($array['duration']);
        $this->assertNull($array['byDay']);
        $this->assertNull($array['byMonth']);

        $this->assertIsArray($array['byMonthDay']);
        $this->assertEmpty($array['byMonthDay']);
        $this->assertIsArray($array['byMonthWeek']);
        $this->assertEmpty($array['byMonthWeek']);
        $this->assertIsArray($array['exceptDates']);
        $this->assertEmpty($array['exceptDates']);

        $this->assertSame(ScheduleInterval::DAILY->value, $array['repeatFrequency']);
        $this->assertNull($array['repeatCount']);

        $this->assertSame(date_default_timezone_get(), $array['timezone']);
    }

    public function testFromJsonRoundTrip(): void
    {
        $start = new ChronosDate('2024-08-01');
        $end = new ChronosDate('2024-08-31');
        $startTime = new ChronosTime('09:00');
        $endTime = new ChronosTime('11:00');
        $except = [
            new ChronosDate('2024-08-15'),
            new ChronosDate('2024-08-18')
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

        $originalArray = $schedule->jsonSerialize();

        $json = json_encode($schedule, JSON_THROW_ON_ERROR);
        $this->assertIsString($json);

        $restored = Schedule::fromJson($json);
        $this->assertInstanceOf(Schedule::class, $restored);

        $restoredArray = $restored->jsonSerialize();

        $this->assertEquals($originalArray, $restoredArray);
    }

    public function testFromJsonWithDurationOnly(): void
    {
        $payload = [
            'repeatFrequency' => ScheduleInterval::DAILY->value,
            'startDate' => '2024-09-01T00:00:00+00:00',
            'endDate' => '2024-09-05T00:00:00+00:00',
            'startTime' => '09:00:00',
            'endTime' => null,
            'duration' => 'P0Y0M0DT1H30M0S',
            'repeatCount' => 5,
            'timezone' => 'UTC',
            'byDay' => [DayOfWeek::MONDAY->value, DayOfWeek::WEDNESDAY->value],
            'byMonthDay' => [1],
            'byMonth' => [Month::SEPTEMBER->value],
            'byMonthWeek' => [1],
            'exceptDates' => ['2024-09-03T00:00:00+00:00'],
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $schedule = Schedule::fromJson($json);

        $arr = $schedule->jsonSerialize();

        $this->assertSame('10:30:00', $arr['endTime']);
        $this->assertSame('09:00:00', $arr['startTime']);
        $this->assertSame('P0Y0M0DT1H30M0S', $arr['duration']);
        $this->assertSame('UTC', $arr['timezone']);
        $this->assertEquals([DayOfWeek::MONDAY->value, DayOfWeek::WEDNESDAY->value], $arr['byDay']);
        $this->assertEquals([1], $arr['byMonthDay']);
        $this->assertEquals([Month::SEPTEMBER->value], $arr['byMonth']);
        $this->assertEquals([1], $arr['byMonthWeek']);
        $this->assertEquals(['2024-09-03T00:00:00+00:00'], $arr['exceptDates']);
    }

    public function testFromJsonInvalidPayloadThrows(): void
    {
        $this->expectException(ScheduleException::class);
        Schedule::fromJson('{"repeatFrequency": 123}');
    }

    public function testFromJsonInvalidJsonThrows(): void
    {
        $this->expectException(ScheduleException::class);
        Schedule::fromJson('{not valid json}');
    }
}