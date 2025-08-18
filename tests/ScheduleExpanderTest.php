<?php

namespace WebDevelovers\Schedule\Tests;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use PHPUnit\Framework\TestCase;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Holiday\HolidayProviderInterface;
use WebDevelovers\Schedule\Schedule;
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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(0, $occurrences);
    }

    public function testUsesDefaultTimezoneWhenNotSpecified(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2024-01-10'),
            startTime: self::chronosTime('12:00')
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(1, $occurrences);
        $this->assertSame(date_default_timezone_get(), $occurrences[0]->start->getTimezone()->getName());
        $this->assertSame(date_default_timezone_get(), $occurrences[0]->end->getTimezone()->getName());
    }

    public function testUsesProvidedTimezone(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2024-01-10'),
            startTime: self::chronosTime('12:00'),
            endTimeOrDuration: 'PT1H',
            timezone: 'Europe/Rome'
        );

        $expander = new ScheduleExpander($schedule, $this->holidaysProvider);
        $occurrences = iterator_to_array($expander->expand());
        $this->assertCount(1, $occurrences);
        $this->assertSame('Europe/Rome', $occurrences[0]->start->getTimezone()->getName());
        $this->assertSame('Europe/Rome', $occurrences[0]->end->getTimezone()->getName());
    }

    public function testNonRecurring(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2024-01-10'),
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

        $this->assertCount(1, $occurrences);
        $this->assertEquals('2024-01-10 00:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-10 00:00', $occurrences[0]->end->format('Y-m-d H:i'));
    }

    public function testNonRecurringWithStartTimeNoEndOrDuration(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: self::chronosDate('2024-01-10'),
            startTime: self::chronosTime('15:30'),
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(1, $occurrences);
        $this->assertEquals('2024-01-10 09:15', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-10 10:00', $occurrences[0]->end->format('Y-m-d H:i'));
    }

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(1, $occurrences);
        $this->assertEquals('2024-05-10 10:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-05-10 10:30', $occurrences[0]->end->format('Y-m-d H:i'));
    }

    public function testDailyWithoutDurationProducesNoOccurrences(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: self::chronosDate('2024-01-01'),
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
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
        $expander = new ScheduleExpander($schedule, $this->holidaysProvider);

        $occurrences = iterator_to_array($expander->expand());

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
        $expander = new ScheduleExpander($schedule, $this->holidaysProvider);

        $occurrences = iterator_to_array($expander->expand());

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

        $this->assertCount(2, $occurrences);
        $this->assertEquals('2024-02-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-02-02 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
    }

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(0, $occurrences);
    }

    public function testDailySkipHolidayOccurrences(): void
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

        $expander = new ScheduleExpander($schedule, $holidayProvider);
        $occurrences = iterator_to_array($expander->expand());
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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-04-10 10:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-05-10 10:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-06-10 10:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testYearlySchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::YEARLY,
            startDate: self::chronosDate('2022-02-20'),
            startTime: self::chronosTime('11:00'),
            endTimeOrDuration: 'PT2H',
            repeatCount: 3,
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2022-02-20 11:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2023-02-20 11:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-02-20 11:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

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

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

        $this->assertCount(2, $occurrences);
        $this->assertEquals('2024-03-04 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-03-18 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('Monday', $occurrences[0]->start->format('l'));
        $this->assertEquals('Monday', $occurrences[1]->start->format('l'));
    }

    private static function chronosDate(string $date): ChronosDate
    {
        return new ChronosDate($date);
    }

    private static function chronosTime(string $time): ChronosTime
    {
        return new ChronosTime($time);
    }

    private function expander(Schedule $schedule): ScheduleExpander
    {
        return new ScheduleExpander($schedule, $this->holidaysProvider);
    }
}