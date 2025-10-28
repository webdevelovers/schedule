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
    private string $tz = 'UTC';

    public function testSimpleConstruction()
    {
        $schedule = new Schedule(ScheduleInterval::DAILY);
        $this->assertEquals(ScheduleInterval::DAILY, $schedule->repeatInterval);
    }

    public function testIdentifierIsDeterministicForSameData(): void
    {
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-01-01'),
            endDate: new ChronosDate('2024-01-31'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 5,
            byDay: [DayOfWeek::MONDAY, DayOfWeek::WEDNESDAY],
            byMonthDay: [1, 15],
            byMonth: [Month::JANUARY],
            byMonthWeek: [1, -1],
            exceptDates: [new ChronosDate('2024-01-10')],
            timezone: $this->tz
        );

        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-01-01'),
            endDate: new ChronosDate('2024-01-31'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 5,
            byDay: [DayOfWeek::MONDAY, DayOfWeek::WEDNESDAY],
            byMonthDay: [1, 15],
            byMonth: [Month::JANUARY],
            byMonthWeek: [1, -1],
            exceptDates: [new ChronosDate('2024-01-10')],
            timezone: $this->tz
        );

        $this->assertSame($s1->identifier, $s2->identifier);
    }

    public function testIdentifierChangesWhenRelevantFieldChanges(): void
    {
        $base = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-01-01'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $changed = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-01-02'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $this->assertNotSame($base->identifier, $changed->identifier);
    }

    public function testIdentifierIsRecomputedInFromArrayIgnoringExternalIdentifier(): void
    {
        $s = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-01-01'),
            endDate: new ChronosDate('2024-01-02'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $data = $s->toArray();
        $this->assertArrayHasKey('identifier', $data);

        $data['identifier'] = 'FORGED_IDENTIFIER';

        $recreated = Schedule::fromArray($data);

        $this->assertSame($s->identifier, $recreated->identifier);
    }

    public function testIdentifierIsPreservedAcrossSerializeCycle(): void
    {
        $s = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-02-01'),
            endDate: new ChronosDate('2024-02-05'),
            startTime: new ChronosTime('08:00'),
            endTimeOrDuration: 'PT30M',
            timezone: $this->tz
        );

        $id1 = $s->identifier;

        $serialized = serialize($s);
        /** @var Schedule $unser */
        $unser = unserialize($serialized);

        $this->assertSame($id1, $unser->identifier);
    }

    public function testIdentifierDiffersIfExceptDatesChange(): void
    {
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-03-01'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            exceptDates: [],
            timezone: $this->tz
        );

        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-03-01'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            exceptDates: [new ChronosDate('2024-03-02')],
            timezone: $this->tz
        );

        $this->assertNotSame($s1->identifier, $s2->identifier);
    }

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

    public function testDurationIsNotCalculatedWithoutEndTime()
    {
        $startTime = new ChronosTime('09:00');

        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: null,
            endDate: null,
            startTime: $startTime,
        );

        $this->assertEquals(null, $schedule->duration);
    }

    public function testInvalidDurationString()
    {
        $this->expectException(ScheduleException::class);
        new Schedule(
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

    public function testByMonthWeekMultipleValuesAccepted()
    {
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
        $this->assertSame('2024/08/01', $array['startDate']);
        $this->assertSame('2024/08/31', $array['endDate']);
        $this->assertSame('09:00:00', $array['startTime']);
        $this->assertSame('11:00:00', $array['endTime']);
        $this->assertSame('P0Y0M0DT2H0M0S', $array['duration']);
        $this->assertSame(ScheduleInterval::DAILY->value, $array['repeatInterval']);
        $this->assertSame(10, $array['repeatCount']);
        $this->assertSame('Europe/Rome', $array['timezone']);
        $this->assertEquals([DayOfWeek::MONDAY->value], $array['byDay']);
        $this->assertEquals([1, 15], $array['byMonthDay']);
        $this->assertEquals([Month::AUGUST->value], $array['byMonth']);
        $this->assertEquals([1], $array['byMonthWeek']);
        $this->assertEquals([
            $except[0]->format('Y/m/d'),
            $except[1]->format('Y/m/d')
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

        $this->assertSame(ScheduleInterval::DAILY->value, $array['repeatInterval']);
        $this->assertNull($array['repeatCount']);

        $this->assertSame(date_default_timezone_get(), $array['timezone']);
    }

    public function testToArrayFromArrayRoundTrip(): void
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

        $array = $schedule->toArray();
        $restored = Schedule::fromArray($array);

        $this->assertEquals($schedule->toArray(), $restored->toArray());
        $this->assertSame($schedule->repeatInterval, $restored->repeatInterval);
        $this->assertEquals($schedule->startDate, $restored->startDate);
        $this->assertEquals($schedule->endDate, $restored->endDate);
        $this->assertEquals($schedule->startTime, $restored->startTime);
        $this->assertEquals($schedule->endTime, $restored->endTime);
        $this->assertSame($schedule->timezone->getName(), $restored->timezone->getName());
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
            'repeatInterval' => ScheduleInterval::DAILY->value,
            'startDate' => '2024/09/01',
            'endDate' => '2024/09/05',
            'startTime' => '09:00:00',
            'endTime' => null,
            'duration' => 'P0Y0M0DT1H30M0S',
            'repeatCount' => 5,
            'timezone' => 'UTC',
            'byDay' => [DayOfWeek::MONDAY->value, DayOfWeek::WEDNESDAY->value],
            'byMonthDay' => [1],
            'byMonth' => [Month::SEPTEMBER->value],
            'byMonthWeek' => [1],
            'exceptDates' => ['2024/09/03'],
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
        $this->assertEquals(['2024/09/03'], $arr['exceptDates']);
    }

    public function testFromJsonInvalidPayloadThrows(): void
    {
        $this->expectException(ScheduleException::class);
        Schedule::fromJson('{"repeatInterval": 123}');
    }

    public function testFromJsonInvalidJsonThrows(): void
    {
        $this->expectException(ScheduleException::class);
        Schedule::fromJson('{not valid json}');
    }

    public function testSerializeUnserializeRoundTrip(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_WEEK,
            startDate: new ChronosDate('2024-08-01'),
            endDate: new ChronosDate('2024-08-31'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: new ChronosTime('11:00'),
            repeatCount: 10,
            byDay: [DayOfWeek::MONDAY, DayOfWeek::WEDNESDAY],
            byMonthDay: [1, 15],
            byMonth: [Month::AUGUST],
            byMonthWeek: [1, -1],
            exceptDates: [new ChronosDate('2024-08-15')],
            timezone: 'Europe/Rome'
        );

        $serialized = serialize($schedule);
        $this->assertIsString($serialized);

        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(Schedule::class, $unserialized);

        $this->assertEquals($schedule->toArray(), $unserialized->toArray());
        $this->assertSame($schedule->repeatInterval, $unserialized->repeatInterval);
        $this->assertEquals($schedule->startDate, $unserialized->startDate);
        $this->assertEquals($schedule->endDate, $unserialized->endDate);
    }

    public function testToJsonMethod(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-08-01'),
            endDate: new ChronosDate('2024-08-31'),
            timezone: 'UTC'
        );

        $json = $schedule->toJson();
        $this->assertIsString($json);

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('repeatInterval', $decoded);
        $this->assertSame(ScheduleInterval::DAILY->value, $decoded['repeatInterval']);
    }

    public function testToJsonIncludesIdentifier(): void
    {
        $s = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-01-01'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: 'UTC'
        );

        $json = $s->toJson();
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('identifier', $decoded);
        $this->assertSame($s->identifier, $decoded['identifier']);
    }

    public function testFromJsonIgnoresExternalIdentifier(): void
    {
        $s = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2024-01-01'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: 'UTC'
        );

        $arr = $s->toArray();
        $arr['identifier'] = 'FORGED';
        $json = json_encode($arr, JSON_THROW_ON_ERROR);

        $restored = Schedule::fromJson($json);
        $this->assertSame($s->identifier, $restored->identifier);
    }

    public function testFromArrayMissingRepeatIntervalThrows(): void
    {
        $this->expectException(ScheduleException::class);
        $this->expectExceptionMessage('Missing or invalid "repeatInterval"');

        Schedule::fromArray([
            'startDate' => '2024/08/01',
        ]);
    }

    public function testFromArrayInvalidRepeatIntervalThrows(): void
    {
        $this->expectException(ScheduleException::class);

        Schedule::fromArray([
            'repeatInterval' => 'invalid_interval',
        ]);
    }

    public function testFromArrayWithNullableFields(): void
    {
        $array = [
            'repeatInterval' => ScheduleInterval::DAILY->value,
            'startDate' => null,
            'endDate' => null,
            'startTime' => null,
            'endTime' => null,
            'duration' => null,
            'repeatCount' => null,
            'timezone' => 'UTC',
            'byDay' => null,
            'byMonthDay' => null,
            'byMonth' => null,
            'byMonthWeek' => null,
            'exceptDates' => null,
        ];

        $schedule = Schedule::fromArray($array);

        $this->assertInstanceOf(Schedule::class, $schedule);
        $this->assertNull($schedule->startDate);
        $this->assertNull($schedule->endDate);
        $this->assertNull($schedule->startTime);
        $this->assertEmpty($schedule->byDay);
        $this->assertEmpty($schedule->byMonth);
    }
}