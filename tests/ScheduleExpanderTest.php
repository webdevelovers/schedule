<?php

namespace WebDevelovers\Schedule\Tests;

use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Exception\ScheduleException;
use WebDevelovers\Schedule\Exception\ScheduleExpandException;
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
/*
    public function testDailyScheduleWithRepeatCount(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: $this->dt('2024-01-01'),
            startTime: $this->dt('2024-01-01 09:00'),
            endTime: $this->dt('2024-01-01 10:00'),
            repeatCount: 3,
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-01-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-03 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testEndDateIncludedInOccurrences(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: $this->dt('2024-01-01'),
            endDate: $this->dt('2024-01-03'),
            startTime: $this->dt('2024-01-01 09:00'),
            duration: 'PT1H',
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-01-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-03 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testWeeklyByDayFilter(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: $this->dt('2024-01-01'),
            startTime: $this->dt('2024-01-01 09:00'),
            repeatCount: 5,
            duration: 'PT1H',
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

    public function testExclusionDates(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: $this->dt('2024-01-01'),
            startTime: $this->dt('2024-01-01 09:00'),
            endTime: $this->dt('2024-01-01 10:00'),
            repeatCount: 3,
            exceptDates: [$this->dt('2024-01-02')],
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-01-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-03 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-01-04 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testMaxOccurrencesLimitsOutput(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: $this->dt('2024-01-01'),
            startTime: $this->dt('2024-01-01 09:00'),
            repeatCount: null,
            duration: 'PT1H',
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(2, $occurrences);
    }

    public function testMonthWeekFirstMonday(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: $this->dt('2024-01-01'),
            startTime: $this->dt('2024-01-01 09:00'),
            repeatCount: 3,
            duration: 'PT1H',
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

    public function testLastWeekOfMonthWithNegativeByMonthWeek(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new DateTimeImmutable('2024-01-01'),
            duration: 'PT1H',
            byDay: [DayOfWeek::FRIDAY], // Solo ultima settimana di ciascun mese
            byMonthWeek: [-1], // Solo venerdì per esempio
        );

        $expander = new ScheduleExpander($schedule, new SampleYasumiProvider('Italy'));

        // Espandiamo per 3 mesi
        $occurrences = iterator_to_array($expander->expand(
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-03-31')
        ));

        // Controlliamo che tutte le date siano davvero i venerdì dell'ultima settimana del mese
        $this->assertNotEmpty($occurrences);

        foreach ($occurrences as $occurrence) {
            assert($occurrence instanceof ScheduleOccurrence);
            $date = $occurrence->start;
            $this->assertSame(DayOfWeek::FRIDAY, DayOfWeek::fromDateTime($date));

            // Calcolo: la settimana di questo giorno deve essere l'ultima del mese
            $lastDay = (clone $date)->modify('last day of this month');
            $weeksInMonth = (int) ceil($lastDay->format('j') / 7);
            $weekOfMonth = (int) ceil($date->format('j') / 7);
            $negativeWeek = $weekOfMonth - ($weeksInMonth + 1);
            $this->assertSame(-1, $negativeWeek, $date->format('Y-m-d')." non è nell'ultima settimana del mese");
        }
    }

    public function testPenultimateWeekOfMonthWithNegativeByMonthWeek(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new DateTimeImmutable('2024-01-01'),
            duration: 'PT1H',
            byDay: [DayOfWeek::MONDAY], // Penultima settimana
            byMonthWeek: [-2], // Solo lunedì
        );

        $expander = new ScheduleExpander($schedule, new SampleYasumiProvider('Italy'));

        $occurrences = iterator_to_array($expander->expand(
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-03-31')
        ));

        $this->assertNotEmpty($occurrences);

        foreach ($occurrences as $occurrence) {
            assert($occurrence instanceof ScheduleOccurrence);
            $date = $occurrence->start;
            $this->assertSame(DayOfWeek::MONDAY, DayOfWeek::fromDateTime($date));

            $lastDay = (clone $date)->modify('last day of this month');
            $weeksInMonth = (int) ceil($lastDay->format('j') / 7);
            $weekOfMonth = (int) ceil($date->format('j') / 7);
            $negativeWeek = $weekOfMonth - ($weeksInMonth + 1);
            $this->assertSame(-2, $negativeWeek, $date->format('Y-m-d')." non è nella penultima settimana del mese");
        }
    }

    public function testSkipHolidayOccurrences(): void
    {
        $holidayProvider = $this->createMock(HolidayProviderInterface::class);
        $holidayProvider
            ->method('isHoliday')
            ->willReturnCallback(fn(\DateTimeInterface $date) => $date->format('Y-m-d') === '2024-01-01');

        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new DateTimeImmutable('2024-01-01'),
            startTime: new DateTimeImmutable('2024-01-01 09:00'),
            repeatCount: 3,
            duration: 'PT1H',
            timezone: $this->tz
        );

        $expander = new ScheduleExpander($schedule, $holidayProvider);
        $occurrences = iterator_to_array($expander->expand());
        $this->assertCount(3, $occurrences);

        // Verifica che '2024-01-02' sia saltato (perché festivo)
        foreach ($occurrences as $occurrence) {
            $this->assertNotEquals('2024-01-01', $occurrence->start->format('Y-m-d'));
        }
    }

    public function testWeeklySchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::WEEKLY,
            startDate: new DateTimeImmutable('2024-05-01'),
            startTime: new DateTimeImmutable('2024-05-01 08:00'),
            repeatCount: 3,
            duration: 'PT1H',
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-05-01 08:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-05-08 08:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-05-15 08:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testMonthlySchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::MONTHLY,
            startDate: new DateTimeImmutable('2024-04-10'),
            startTime: new DateTimeImmutable('2024-04-10 10:00'),
            repeatCount: 3,
            duration: 'PT30M',
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
            startDate: new DateTimeImmutable('2022-02-20'),
            startTime: new DateTimeImmutable('2022-02-20 11:00'),
            repeatCount: 3,
            duration: 'PT2H',
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(3, $occurrences);
        $this->assertEquals('2022-02-20 11:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2023-02-20 11:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-02-20 11:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testEveryTwoWeeksSchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_TWO_WEEKS,
            startDate: new DateTimeImmutable('2024-03-01'),
            startTime: new DateTimeImmutable('2024-03-01 09:00'),
            repeatCount: 3,
            duration: 'PT1H',
            timezone: $this->tz
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());
        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-03-01 09:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-03-15 09:00', $occurrences[1]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-03-29 09:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }

    public function testEveryThreeMonthsSchedule(): void
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_THREE_MONTHS,
            startDate: new DateTimeImmutable('2024-01-31'),
            startTime: new DateTimeImmutable('2024-01-31 18:00'),
            repeatCount: 3,
            duration: 'PT1H',
            timezone: $this->tz,
        );

        $occurrences = iterator_to_array($this->expander($schedule)->expand());

        $this->assertCount(3, $occurrences);
        $this->assertEquals('2024-01-31 18:00', $occurrences[0]->start->format('Y-m-d H:i'));
        $this->assertEquals('2024-05-01 18:00', $occurrences[1]->start->format('Y-m-d H:i')); // attento ai mesi più corti!
        $this->assertEquals('2024-08-01 18:00', $occurrences[2]->start->format('Y-m-d H:i'));
    }*/

    /** @throws Exception */
    private function dt(string $date): \DateTimeInterface
    {
        return new \DateTime($date, new \DateTimeZone($this->tz));
    }

    private function expander(Schedule $schedule): ScheduleExpander
    {
        return new ScheduleExpander($schedule, $this->holidaysProvider);
    }
}