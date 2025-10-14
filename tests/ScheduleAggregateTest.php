<?php

namespace WebDevelovers\Schedule\Tests;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use JsonException;
use PHPUnit\Framework\TestCase;
use stdClass;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Exception\ScheduleException;
use WebDevelovers\Schedule\Schedule;
use WebDevelovers\Schedule\ScheduleAggregate;

class ScheduleAggregateTest extends TestCase
{
    /** @throws ScheduleException */
    private function makeSchedule(
        string $startDate = '2025-01-01',
        ?string $endDate = '2025-01-31',
        string $startTime = '09:00:00',
        string $endTime = '11:00:00'
    ): Schedule {
        return new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate($startDate),
            endDate: $endDate ? new ChronosDate($endDate) : null,
            startTime: new ChronosTime($startTime),
            endTimeOrDuration: new ChronosTime($endTime)
        );
    }

    /** @throws ScheduleException */
    public function testConstructWithSchedulesAndAll(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-01-11', '2025-01-20');

        $agg = new ScheduleAggregate([$s1, $s2]);

        $all = $agg->all();
        $this->assertCount(2, $all);
        $this->assertSame($s1, $all[0]);
        $this->assertSame($s2, $all[1]);
    }

    public function testConstructWithInvalidElementThrows(): void
    {
        $this->expectException(ScheduleException::class);
        /** @phpstan-ignore-next-line */
        new ScheduleAggregate([new stdClass()]);
    }

    /** @throws ScheduleException */
    public function testWithAddedReturnsNewInstanceAndPreservesOriginal(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $agg = new ScheduleAggregate([$s1]);

        $s2 = $this->makeSchedule('2025-02-01', '2025-02-10');
        $agg2 = $agg->withAdded($s2);

        $this->assertCount(1, $agg->all());
        $this->assertCount(2, $agg2->all());
        $this->assertSame($s1, $agg2->all()[0]);
        $this->assertSame($s2, $agg2->all()[1]);
    }

    /** @throws ScheduleException */
    public function testWithSchedulesReplacesAll(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-02-01', '2025-02-10');

        $agg = new ScheduleAggregate([$s1]);
        $agg2 = $agg->withSchedules([$s2]);

        $this->assertCount(1, $agg->all());
        $this->assertSame($s1, $agg->all()[0]);

        $this->assertCount(1, $agg2->all());
        $this->assertSame($s2, $agg2->all()[0]);
    }

    /** @throws ScheduleException */
    public function testGetBoundsWithMixedNulls(): void
    {
        $s1 = $this->makeSchedule('2025-03-05', '2025-03-20');
        $s2 = $this->makeSchedule('2025-02-01', null); // endDate null
        $s3 = $this->makeSchedule('2025-04-01', '2025-04-10');

        $agg = new ScheduleAggregate([$s1, $s2, $s3]);

        [$minStart, $maxEnd] = $agg->getBounds();
        $this->assertNotNull($minStart);
        $this->assertSame('2025-02-01', $minStart?->toDateString());
        $this->assertSame('2025-04-10', $maxEnd?->toDateString());
    }

    /** @throws ScheduleException */
    public function testGetBoundsAllOpenEnds(): void
    {
        $s1 = $this->makeSchedule('2025-01-10', null);
        $s2 = $this->makeSchedule('2025-01-01', null);

        $agg = new ScheduleAggregate([$s1, $s2]);

        [$minStart, $maxEnd] = $agg->getBounds();
        $this->assertSame('2025-01-01', $minStart?->toDateString());
        $this->assertNull($maxEnd);
    }

    /** @throws ScheduleException */
    public function testIntersectingFiltersCorrectly(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-01-15', '2025-01-20');
        $s3 = $this->makeSchedule('2025-02-01', '2025-02-10');

        $agg = new ScheduleAggregate([$s1, $s2, $s3]);

        // Interval intersecting s1, s2 but not s3
        $subset = $agg->intersecting(new ChronosDate('2025-01-05'), new ChronosDate('2025-01-18'));
        $this->assertCount(2, $subset->all());
        $this->assertSame([$s1, $s2], $subset->all());
    }

    /** @throws ScheduleException */
    public function testIntersectingWithOpenSchedule(): void
    {
        $sOpen = $this->makeSchedule('2025-01-01', null);
        $sClosed = $this->makeSchedule('2025-03-01', '2025-03-05');

        $agg = new ScheduleAggregate([$sOpen, $sClosed]);

        $subset = $agg->intersecting(new ChronosDate('2025-02-01'), new ChronosDate('2025-02-10'));
        $this->assertCount(1, $subset->all());
        $this->assertSame($sOpen, $subset->all()[0]);
    }

    public function testIntersectingInvalidRangeThrows(): void
    {
        $this->expectException(ScheduleException::class);

        $agg = new ScheduleAggregate();
        $agg->intersecting(new ChronosDate('2025-02-10'), new ChronosDate('2025-02-01'));
    }

    /** @throws ScheduleException */
    public function testMergeAggregates(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-02-01', '2025-02-10');
        $s3 = $this->makeSchedule('2025-03-01', '2025-03-10');

        $a1 = new ScheduleAggregate([$s1]);
        $a2 = new ScheduleAggregate([$s2, $s3]);

        $merged = $a1->merge($a2);

        $this->assertCount(3, $merged->all());
        $this->assertSame([$s1, $s2, $s3], $merged->all());
        // Immutability
        $this->assertCount(1, $a1->all());
        $this->assertCount(2, $a2->all());
    }

    /** @throws ScheduleException */
    public function testJsonSerializeIncludesBoundsAndSchedules(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-01-20', '2025-01-25');

        $agg = new ScheduleAggregate([$s1, $s2]);
        $arr = $agg->jsonSerialize();

        $this->assertSame('ScheduleAggregate', $arr['type']);
        $this->assertArrayHasKey('schedules', $arr);
        $this->assertCount(2, $arr['schedules']);
        $this->assertArrayHasKey('bounds', $arr);
        $this->assertSame('2025-01-01T00:00:00+00:00', $arr['bounds']['startDate']);
        $this->assertSame('2025-01-25T00:00:00+00:00', $arr['bounds']['endDate']);
    }

    /** @throws ScheduleException */
    public function testFromJsonRoundTrip(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-02-01', '2025-02-05');

        $agg = new ScheduleAggregate([$s1, $s2]);

        $json = json_encode($agg, JSON_THROW_ON_ERROR);
        $restored = ScheduleAggregate::fromJson($json);

        $this->assertInstanceOf(ScheduleAggregate::class, $restored);
        $this->assertCount(2, $restored->all());

        $this->assertEquals($agg->jsonSerialize(), $restored->jsonSerialize());
    }

    public function testFromJsonInvalidJsonThrows(): void
    {
        $this->expectException(ScheduleException::class);
        ScheduleAggregate::fromJson('{invalid}');
    }

    public function testFromJsonMissingSchedulesThrows(): void
    {
        $this->expectException(ScheduleException::class);
        ScheduleAggregate::fromJson('{"type":"ScheduleAggregate"}');
    }

    public function testFromJsonSchedulesElementNotObjectThrows(): void
    {
        $this->expectException(ScheduleException::class);
        ScheduleAggregate::fromJson('{"schedules":[123]}');
    }

    /** @throws ScheduleException */
    public function testIntersectingNoOverlapReturnsEmpty(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-02-01', '2025-02-10');

        $agg = new ScheduleAggregate([$s1, $s2]);
        $subset = $agg->intersecting(new ChronosDate('2025-01-11'), new ChronosDate('2025-01-15'));

        $this->assertCount(0, $subset->all());
    }

    /** @throws ScheduleException */
    public function testToArrayFromArrayRoundTrip(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-02-01', '2025-02-05');

        $agg = new ScheduleAggregate([$s1, $s2]);

        $array = $agg->toArray();
        $restored = ScheduleAggregate::fromArray($array);

        $this->assertInstanceOf(ScheduleAggregate::class, $restored);
        $this->assertCount(2, $restored->all());
        $this->assertEquals($agg->toArray(), $restored->toArray());
    }

    /** @throws ScheduleException */
    public function testSerializeUnserializeRoundTrip(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-02-01', '2025-02-05');

        $agg = new ScheduleAggregate([$s1, $s2]);

        $serialized = serialize($agg);
        $this->assertIsString($serialized);

        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(ScheduleAggregate::class, $unserialized);

        $this->assertCount(2, $unserialized->all());
        $this->assertEquals($agg->toArray(), $unserialized->toArray());
    }

    /** @throws JsonException */
    public function testToJsonMethod(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-02-01', '2025-02-05');

        $agg = new ScheduleAggregate([$s1, $s2]);

        $json = $agg->toJson();
        $this->assertIsString($json);

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertSame('ScheduleAggregate', $decoded['type']);
        $this->assertArrayHasKey('schedules', $decoded);
        $this->assertCount(2, $decoded['schedules']);
        $this->assertArrayHasKey('bounds', $decoded);
    }

    public function testFromArrayMissingSchedulesThrows(): void
    {
        $this->expectException(ScheduleException::class);
        $this->expectExceptionMessage('Missing "schedules" array');

        ScheduleAggregate::fromArray([
            'type' => 'ScheduleAggregate',
        ]);
    }

    public function testFromArrayInvalidScheduleElementThrows(): void
    {
        $this->expectException(ScheduleException::class);

        ScheduleAggregate::fromArray([
            'schedules' => [
                ['repeatInterval' => 'invalid'],
            ],
        ]);
    }

    /** @throws ScheduleException */
    public function testFromArrayWithEmptySchedules(): void
    {
        $agg = ScheduleAggregate::fromArray([
            'schedules' => [],
        ]);

        $this->assertInstanceOf(ScheduleAggregate::class, $agg);
        $this->assertCount(0, $agg->all());
    }

    /** @throws ScheduleException */
    public function testJsonSerializeMatchesToArray(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-02-01', '2025-02-05');

        $agg = new ScheduleAggregate([$s1, $s2]);

        $jsonSerialized = $agg->jsonSerialize();
        $toArray = $agg->toArray();

        $this->assertEquals($toArray, $jsonSerialized);
    }

    /** @throws ScheduleException */
    public function testSortedByStartDateAscending(): void
    {
        $s1 = $this->makeSchedule('2025-03-01', '2025-03-10');
        $s2 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s3 = $this->makeSchedule('2025-02-01', '2025-02-10');

        $agg = new ScheduleAggregate([$s1, $s2, $s3]);
        $sorted = $agg->sortedBy('startDate');

        $dates = array_map(
            static fn (Schedule $s) => $s->startDate?->format('Y-m-d'),
            $sorted->all()
        );

        $this->assertSame(['2025-01-01', '2025-02-01', '2025-03-01'], $dates);

        // Original should remain unchanged
        $this->assertSame('2025-03-01', $agg->all()[0]->startDate?->format('Y-m-d'));
    }

    /** @throws ScheduleException */
    public function testSortedByStartDateDescending(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $s2 = $this->makeSchedule('2025-03-01', '2025-03-10');
        $s3 = $this->makeSchedule('2025-02-01', '2025-02-10');

        $agg = new ScheduleAggregate([$s1, $s2, $s3]);
        $sorted = $agg->sortedBy('startDate', false);

        $dates = array_map(
            static fn (Schedule $s) => $s->startDate?->format('Y-m-d'),
            $sorted->all()
        );

        $this->assertSame(['2025-03-01', '2025-02-01', '2025-01-01'], $dates);
    }

    /** @throws ScheduleException */
    public function testSortedByStartDateWithNulls(): void
    {
        $s1 = $this->makeSchedule('2025-02-01', '2025-02-10');
        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: null,
            startTime: new ChronosTime('09:00:00'),
            endTimeOrDuration: new ChronosTime('11:00:00')
        );
        $s3 = $this->makeSchedule('2025-01-01', '2025-01-10');

        $agg = new ScheduleAggregate([$s1, $s2, $s3]);
        $sorted = $agg->sortedBy('startDate');

        $all = $sorted->all();
        $this->assertSame('2025-01-01', $all[0]->startDate?->format('Y-m-d'));
        $this->assertSame('2025-02-01', $all[1]->startDate?->format('Y-m-d'));
        $this->assertNull($all[2]->startDate); // Null goes to the end
    }

    /** @throws ScheduleException */
    public function testSortedByEndDate(): void
    {
        $s1 = $this->makeSchedule('2025-01-01', '2025-03-31');
        $s2 = $this->makeSchedule('2025-01-01', '2025-01-31');
        $s3 = $this->makeSchedule('2025-01-01', '2025-02-28');

        $agg = new ScheduleAggregate([$s1, $s2, $s3]);
        $sorted = $agg->sortedBy('endDate');

        $dates = array_map(
            static fn (Schedule $s) => $s->endDate?->format('Y-m-d'),
            $sorted->all()
        );

        $this->assertSame(['2025-01-31', '2025-02-28', '2025-03-31'], $dates);
    }

    public function testSortedByInvalidFieldThrows(): void
    {
        $this->expectException(ScheduleException::class);
        $this->expectExceptionMessage('Invalid sort field: invalidField');

        $s1 = $this->makeSchedule('2025-01-01', '2025-01-10');
        $agg = new ScheduleAggregate([$s1]);

        $agg->sortedBy('invalidField');
    }
}