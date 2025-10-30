<?php

namespace WebDevelovers\Schedule\Tests;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use Generator;
use PHPUnit\Framework\TestCase;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Exception\ScheduleException;
use WebDevelovers\Schedule\Exception\ScheduleExpandException;
use WebDevelovers\Schedule\Holiday\HolidayProviderInterface;
use WebDevelovers\Schedule\Schedule;
use WebDevelovers\Schedule\ScheduleAggregate;
use WebDevelovers\Schedule\ScheduleExpander;
use WebDevelovers\Schedule\ScheduleOccurrence;

final class ScheduleExpanderTest extends TestCase
{
    private string $tz;
    private HolidayProviderInterface $holidaysProvider;

    protected function setUp(): void
    {
        $this->tz = 'UTC';
        $this->holidaysProvider = $this->createMock(HolidayProviderInterface::class);
        $this->holidaysProvider
            ->method('isHoliday')
            ->willReturn(false);
    }

    public function testReturnsEmptyWhenStartDateMissing(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));
        $this->assertCount(0, $occurrences);
    }

    public function testNonRecurring(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2024-01-10'),
            startTime: self::chronosTime('15:30'),
            endTimeOrDuration: self::chronosTime('16:30'),
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(1, $occurrences);
        $this->assertEquals('2024-01-10 15:30', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-10 16:30', $occurrences[0]->end->format('Y-m-d H:i'));
    }

    //TODO: review before 1.0
    public function testNonRecurringWithStartTimeNoEndOrDuration(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2024-01-10'),
            startTime: self::chronosTime('15:30'),
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(1, $occurrences);
        $this->assertEquals('2024-01-10 15:30', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-10 15:30', $occurrences[0]->end->format('Y-m-d H:i'));
    }

    public function testNonRecurringOvernight(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2024-01-10'),
            startTime: self::chronosTime('22:00'),
            endTimeOrDuration: self::chronosTime('01:00'),
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(1, $occurrences);
        $this->assertEquals('2024-01-10 22:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-11 01:00', $occurrences[0]->end->format('Y-m-d H:i'));
    }

    public function testNonRecurringWithDuration(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2024-01-10'),
            startTime: self::chronosTime('09:15'),
            endTimeOrDuration: 'PT45M',
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(1, $occurrences);
        $this->assertEquals('2024-01-10 09:15', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-10 10:00', $occurrences[0]->end->format('Y-m-d H:i'));
    }

    public function testOccurrenceDurationIsComputedOnCreation(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2024-01-10'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H30M',
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(1, $occurrences);
        $this->assertInstanceOf(\DateInterval::class, $occurrences[0]->duration);
        $this->assertEquals(
            $occurrences[0]->end->format('Y-m-d H:i'),
            $occurrences[0]->start->add($occurrences[0]->duration)->format('Y-m-d H:i'),
            'start + duration has to coincide with end'
        );
    }

    //TODO: review before 1.0
    public function testNonRecurringIgnoresExceptDates(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2024-05-10'),
            startTime: self::chronosTime('10:00'),
            endTimeOrDuration: 'PT30M',
            exceptDates: [self::chronosDate('2024-05-10')],
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(1, $occurrences);
        $this->assertEquals('2024-05-10 10:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-05-10 10:30', $occurrences[0]->end->format('Y-m-d H:i'));
    }

    //TODO: review before 1.0
    public function testDailyWithoutDurationProducesNoOccurrences(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(0, $occurrences);
    }

    public function testDailyRepeatCountsLimitsOutput(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 2,
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(2, $occurrences);
    }

    public function testDailyWithRepeatCount(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: self::chronosTime('10:00'),
            repeatCount: 3,
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-01-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-03 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testDailyOvernightWithRepeatCount(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            startTime: new ChronosTime('23:30', $this->tz),
            endTimeOrDuration: new ChronosTime('01:00', $this->tz),
            repeatCount: 2,
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(2, $occurrences);
        $this->assertEquals('2024-01-01 23:30', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-02 01:00', $occurrences[0]->end->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-02 23:30', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-03 01:00', $occurrences[1]->end->format('Y-m-d H:i'));
    }

    public function testDailyEndDateIncludedInOccurrences(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            endDate: self::chronosDate('2024-01-03'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-01-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-03 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testDailyByDayFilter(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 5,
            byDay: [DayOfWeek::MONDAY, DayOfWeek::WEDNESDAY],
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(5, $occurrences);
        $this->assertEquals('Monday', $occurrences[0]->start->format('l'));
        $this->assertEquals('Wednesday', $occurrences[1]->start->format('l'));
        $this->assertEquals('Monday', $occurrences[2]->start->format('l'));
        $this->assertEquals('Wednesday', $occurrences[3]->start->format('l'));
        $this->assertEquals('Monday', $occurrences[4]->start->format('l'));
    }

    public function testDailyExceptDates(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: self::chronosTime('10:00'),
            repeatCount: 3,
            exceptDates: [self::chronosDate('2024-01-02')],
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-01-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-03 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-04 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testExceptDateAtEndDateBoundaryReducesOccurrences(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            endDate: self::chronosDate('2024-01-03'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            exceptDates: [self::chronosDate('2024-01-03')],
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(2, $occurrences);
        $this->assertEquals('2024-01-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-02 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
    }

    public function testDailyByMonthWeekAndByDay(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            byDay: [DayOfWeek::MONDAY],
            byMonthWeek: [1],
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(3, $occurrences);

        foreach ($occurrences as $occurrence) {
            $this->assertEquals('Monday', $occurrence->start->format('l'));
            $this->assertLessThanOrEqual(7, (int) $occurrence->start->format('j'));
        }
    }

    public function testDailyByMonthWeekWithLastWeekAndByDayFilter(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            byDay: [DayOfWeek::FRIDAY],
            byMonthWeek: [-1],
        );

        $schedule = $schedule->withStartDate(self::chronosDate('2024-01-01'))->withEndDate(self::chronosDate('2024-03-31'));
        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertNotEmpty($occurrences);

        foreach ($occurrences as $occurrence) {
            assert($occurrence instanceof ScheduleOccurrence);
            $date = $occurrence->start;
            $this->assertSame(DayOfWeek::FRIDAY, DayOfWeek::fromDate(new ChronosDate($date)));

            $lastDay = (clone $date)->modify('last day of this month');
            $weeksInMonth = (int) ceil($lastDay->format('j') / 7);
            $weekOfMonth = (int) ceil($date->format('j') / 7);
            $negativeWeek = $weekOfMonth - ($weeksInMonth + 1);
            $this->assertSame(-1, $negativeWeek, $date->format('Y-m-d')." non è nell'ultima settimana del mese");
        }
    }

    public function testDailyPenultimateByMonthWeekAndByDayFilter(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            byDay: [DayOfWeek::MONDAY],
            byMonthWeek: [-2],
        );

        $schedule = $schedule->withStartDate(self::chronosDate('2024-01-01'))->withEndDate(self::chronosDate('2024-03-31'));

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertNotEmpty($occurrences);

        foreach ($occurrences as $occurrence) {
            assert($occurrence instanceof ScheduleOccurrence);
            $date = $occurrence->start;
            $this->assertSame(DayOfWeek::MONDAY, DayOfWeek::fromDate(new ChronosDate($date)));
        }

        $this->assertEquals('2024-01-22 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-02-19 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-03-18 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testByMonthWeekNonExistingWeekProducesNoOccurrences(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-02-01'),
            endDate: self::chronosDate('2024-02-29'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            byDay: [],
            byMonthWeek: [6],
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));
        $this->assertCount(0, $occurrences);
    }

    public function testByMonthExcludingStartMonth(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-15'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 2,
            byMonth: [Month::FEBRUARY],
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(2, $occurrences);
        $this->assertEquals('2024-02-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-02-02 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
    }

    //TODO: review before 1.0
    public function testDailyByMonthDayNegativeNotSupportedProducesNoOccurrences(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-03-01'),
            endDate: self::chronosDate('2024-03-31'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            byMonthDay: [-1],
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));
        $this->assertCount(0, $occurrences);
    }

    public function testHolidayOccurrences(): void
    {
        $holidayProvider = $this->createMock(HolidayProviderInterface::class);
        $holidayProvider
            ->method('isHoliday')
            ->willReturnCallback(fn(ChronosDate $date) => $date->format('Y-m-d') === '2024-01-01');

        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $holidayProvider));
        $this->assertCount(3, $occurrences);

        foreach ($occurrences as $occurrence) {
            assert($occurrence instanceof ScheduleOccurrence);
            if($occurrence->start->format('Y-m-d') === '2024-01-01') {
                $this->assertTrue($occurrence->isHoliday);
            }
        }
    }

    public function testWeeklySchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_WEEK,
            startDate: self::chronosDate('2024-05-01'),
            startTime: self::chronosTime('08:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-05-01 08:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-05-08 08:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-05-15 08:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testWeeklyWithEndDateStopsInclusive(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_WEEK,
            startDate: self::chronosDate('2024-01-03'),
            endDate: self::chronosDate('2024-01-31'),
            startTime: self::chronosTime('08:00'),
            endTimeOrDuration: 'PT30M',
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(5, $occurrences);
        $this->assertEquals('2024-01-03 08:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-31 08:00', $occurrences[4]->start->format('Y-m-d H:i'));
    }

    public function testBiweeklySchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_TWO_WEEKS,
            startDate: self::chronosDate('2024-03-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-03-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-03-15 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-03-29 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testTrimonthlySchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_THREE_MONTHS,
            startDate: self::chronosDate('2024-01-31'),
            startTime: self::chronosTime('18:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz,
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-01-31 18:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-05-01 18:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-08-01 18:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testMonthlySchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_MONTH,
            startDate: self::chronosDate('2024-04-10'),
            startTime: self::chronosTime('10:00'),
            endTimeOrDuration: 'PT30M',
            repeatCount: 3,
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-04-10 10:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-05-10 10:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-06-10 10:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testYearlySchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_YEAR,
            startDate: self::chronosDate('2022-02-20'),
            startTime: self::chronosTime('11:00'),
            endTimeOrDuration: 'PT2H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2022-02-20 11:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2023-02-20 11:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-02-20 11:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    //TODO: test the remaining enum values before 1.0

    public function testCombinedFiltersByDayByMonthByMonthDay(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-03-01'),
            endDate: self::chronosDate('2024-03-31'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            byDay: [DayOfWeek::MONDAY],
            byMonthDay: [4, 18],
            byMonth: [Month::MARCH],
            timezone: $this->tz
        );

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider));

        $this->assertCount(2, $occurrences);
        $this->assertEquals('2024-03-04 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-03-18 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('Monday', $occurrences[0]->start->format('l'));
        $this->assertEquals('Monday', $occurrences[1]->start->format('l'));
    }

    public function testExpandAggregateWithMultipleSchedules(): void
    {
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2025-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 2,
            timezone: $this->tz
        );

        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2025-01-05'),
            startTime: self::chronosTime('14:00'),
            endTimeOrDuration: 'PT30M',
            repeatCount: 2,
            timezone: $this->tz
        );

        $aggregate = new ScheduleAggregate([$s1, $s2]);

        $occurrences = iterator_to_array(ScheduleExpander::expandAggregate($aggregate, $this->holidaysProvider), false);

        $this->assertCount(4, $occurrences);
    }

    public function testExpandAggregateSorted(): void
    {
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2025-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2025-01-02'),
            startTime: self::chronosTime('08:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $aggregate = new ScheduleAggregate([$s1, $s2]);

        $occurrences = iterator_to_array(
            ScheduleExpander::expandAggregateSorted($aggregate, $this->holidaysProvider)
        );

        $this->assertCount(6, $occurrences);

        // Verify they are actually sorted
        $this->assertEquals('2025-01-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-02 08:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-02 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-03 08:00', $occurrences[3]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-03 09:00', $occurrences[4]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-04 08:00', $occurrences[5]->start->format('Y-m-d H:i'));
    }

    public function testExpandAggregateSortedDescending(): void
    {
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2025-01-05'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2025-01-10'),
            startTime: self::chronosTime('14:00'),
            endTimeOrDuration: 'PT30M',
            timezone: $this->tz
        );

        $aggregate = new ScheduleAggregate([$s1, $s2]);

        $occurrences = iterator_to_array(
            ScheduleExpander::expandAggregateSorted($aggregate, $this->holidaysProvider, false)
        );

        $this->assertCount(2, $occurrences);
        $this->assertEquals('2025-01-10 14:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-05 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
    }

    public function testExpandAggregateSortedIsActuallyLazy(): void
    {
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2025-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 1000, // Many occurrences
            timezone: $this->tz
        );

        $generator = ScheduleExpander::expandAggregateSorted(
            new ScheduleAggregate([$s1]),
            $this->holidaysProvider
        );

        // Generator should not consume memory until iterated
        $this->assertInstanceOf(Generator::class, $generator);

        // Get only first 5
        $count = 0;
        foreach ($generator as $occurrence) {
            $count++;
            if ($count === 5) {
                break;
            }
        }

        $this->assertEquals(5, $count);
    }

    public function testExpandAggregateSortedRemovesDuplicates(): void
    {
        // Two schedules that produce the same occurrences
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2025-01-10'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2025-01-10'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $aggregate = new ScheduleAggregate([$s1, $s2]);

        // With unique = true (default)
        $occurrences = iterator_to_array(
            ScheduleExpander::expandAggregateSorted($aggregate, $this->holidaysProvider, true, true)
        );

        $this->assertCount(1, $occurrences); // Only one, duplicate removed
        $this->assertEquals('2025-01-10 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
    }

    public function testExpandAggregateSortedKeepsDuplicatesWhenDisabled(): void
    {
        // Two schedules that produce the same occurrences
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2025-01-10'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2025-01-10'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $aggregate = new ScheduleAggregate([$s1, $s2]);

        $occurrences = iterator_to_array(
            ScheduleExpander::expandAggregateSorted($aggregate, $this->holidaysProvider, true, false)
        );

        $this->assertCount(2, $occurrences); // Both occurrences kept
    }

    public function testExpandAggregateSortedRemovesDuplicatesFromRecurringSchedules(): void
    {
        // Two daily schedules with overlapping dates
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2025-01-01'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2025-01-02'),
            startTime: self::chronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $aggregate = new ScheduleAggregate([$s1, $s2]);

        $occurrences = iterator_to_array(
            ScheduleExpander::expandAggregateSorted($aggregate, $this->holidaysProvider, true, true)
        );

        // Should have: 01-01, 01-02, 01-03, 01-04 (no duplicates on 02, 03)
        $this->assertCount(4, $occurrences);
        $this->assertEquals('2025-01-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-02 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-03 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-04 09:00', $occurrences[3]->start->format('Y-m-d H:i'));
    }

    public function testExpandUsesFromAsStartWhenScheduleStartMissing(): void
    {
        // Schedule senza startDate, ma con durata valida (1h)
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz
        );

        // Range forzato: 2025-01-10..2025-01-12 (3 giorni)
        $from = new ChronosDate('2025-01-10');
        $to   = new ChronosDate('2025-01-12');

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider, $from, $to));
        $this->assertCount(3, $occurrences);
        $this->assertEquals('2025-01-10 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-12 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testExpandStopsAtToWhenScheduleEndMissing(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2025-01-01'),
            startTime: new ChronosTime('08:00'),
            endTimeOrDuration: 'PT30M',
            timezone: $this->tz
        );

        // Range limita l'uscita all'11 gennaio
        $from = new ChronosDate('2025-01-09');
        $to   = new ChronosDate('2025-01-11');

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider, $from, $to));
        $this->assertCount(3, $occurrences);

        $this->assertEquals('2025-01-09 08:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-10 08:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-11 08:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testExpandWithinNarrowWindowYieldsOnlyIntersectingDays(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2025-01-01'),
            endDate: new ChronosDate('2025-01-10'),
            startTime: new ChronosTime('10:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        // Finestra stretta: 2025-01-03..2025-01-04
        $from = new ChronosDate('2025-01-03');
        $to   = new ChronosDate('2025-01-04');

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider, $from, $to));
        $this->assertCount(2, $occurrences);
        $this->assertEquals('2025-01-03 10:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-04 10:00', $occurrences[1]->start->format('Y-m-d H:i'));
    }

    public function testExpandReturnsEmptyWhenWindowBeforeSchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2025-02-01'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 5,
            timezone: $this->tz
        );

        $from = new ChronosDate('2025-01-01');
        $to   = new ChronosDate('2025-01-15');

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider, $from, $to));
        $this->assertCount(0, $occurrences);
    }

    public function testExpandReturnsEmptyWhenWindowAfterSchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2025-01-01'),
            endDate: new ChronosDate('2025-01-05'),
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            timezone: $this->tz
        );

        $from = new ChronosDate('2025-02-01');
        $to   = new ChronosDate('2025-02-10');

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider, $from, $to));
        $this->assertCount(0, $occurrences);
    }

    public function testExpandHonorsByDayWithinWindow(): void
    {
        // Finestra di una settimana, ma filtriamo solo lunedì (2 occorrenze nelle 2 settimane)
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            byDay: [DayOfWeek::MONDAY],
            timezone: $this->tz
        );

        $from = new ChronosDate('2025-01-06'); // Monday
        $to   = new ChronosDate('2025-01-13'); // Next Monday

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider, $from, $to));
        $this->assertCount(2, $occurrences);
        $this->assertEquals('Monday', $occurrences[0]->start->format('l'));
        $this->assertEquals('Monday', $occurrences[1]->start->format('l'));
    }

    public function testAggregateWithWindowMergesOccurrencesFromAllSchedules(): void
    {
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startTime: new ChronosTime('09:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 2,
            timezone: $this->tz
        );

        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startTime: new ChronosTime('14:00'),
            endTimeOrDuration: 'PT30M',
            repeatCount: 2,
            timezone: $this->tz
        );

        $agg = new ScheduleAggregate([$s1, $s2]);

        $from = new ChronosDate('2025-01-01');
        $to   = new ChronosDate('2025-01-02');

        $occurrences = iterator_to_array(ScheduleExpander::expandAggregate($agg, $this->holidaysProvider, from: $from, to: $to));

        $this->assertCount(4, $occurrences);
        $this->assertEquals('09:00', $occurrences[0]->start->format('H:i'));
        $this->assertEquals('09:00', $occurrences[1]->start->format('H:i'));
        $this->assertEquals('14:00', $occurrences[2]->start->format('H:i'));
        $this->assertEquals('14:00', $occurrences[3]->start->format('H:i'));
    }

    public function testAggregateSortedRespectsWindowAndSorting(): void
    {
        $s1 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startTime: new ChronosTime('10:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $s2 = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startTime: new ChronosTime('08:00'),
            endTimeOrDuration: 'PT1H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $agg = new ScheduleAggregate([$s1, $s2]);

        $from = new ChronosDate('2025-01-01');
        $to   = new ChronosDate('2025-01-02');

        $occurrences = iterator_to_array(
            ScheduleExpander::expandAggregateSorted($agg, $this->holidaysProvider, true, true, $from, $to)
        );

        // 2 giorni * 2 schedules = 4 occorrenze
        $this->assertCount(4, $occurrences);

        // Ordinati per start ascendente (08:00,10:00) per ciascun giorno
        $this->assertEquals('08:00', $occurrences[0]->start->format('H:i'));
        $this->assertEquals('10:00', $occurrences[1]->start->format('H:i'));
        $this->assertEquals('08:00', $occurrences[2]->start->format('H:i'));
        $this->assertEquals('10:00', $occurrences[3]->start->format('H:i'));
    }

    public function testExpandWithOnlyFromProducesUnboundedTail(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startTime: new ChronosTime('07:00'),
            endTimeOrDuration: 'PT15M',
            repeatCount: 2,
            timezone: $this->tz
        );

        $from = new ChronosDate('2025-01-20');

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider, $from, null));
        $this->assertCount(2, $occurrences);
        $this->assertEquals('2025-01-20 07:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-21 07:00', $occurrences[1]->start->format('Y-m-d H:i'));
    }

    public function testExpandWithOnlyToCutsHead(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate('2025-01-01'),
            startTime: new ChronosTime('12:00'),
            endTimeOrDuration: 'PT45M',
            timezone: $this->tz
        );

        $to = new ChronosDate('2025-01-03');

        $occurrences = iterator_to_array(ScheduleExpander::expand($schedule, $this->holidaysProvider, null, $to));
        // Dal 1 al 3 inclusi
        $this->assertGreaterThanOrEqual(3, count($occurrences));
        $this->assertEquals('2025-01-01 12:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2025-01-03 12:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    private static function chronosDate(string $date): ChronosDate
    {
        return new ChronosDate($date);
    }

    private static function chronosTime(string $time): ChronosTime
    {
        return new ChronosTime($time);
    }
}