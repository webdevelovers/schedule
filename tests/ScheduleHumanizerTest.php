<?php

namespace WebDevelovers\Schedule\Tests;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Humanizer\HumanizerTranslatorInterface;
use WebDevelovers\Schedule\Humanizer\ScheduleHumanizer;
use WebDevelovers\Schedule\Schedule;

/** @deprecated - use Schedule->toArray instead */
class ScheduleHumanizerTest extends TestCase
{
    private function getTranslator(): HumanizerTranslatorInterface
    {
        $path = __DIR__ . '/../translations/schedule.it.yaml';
        return ArrayTranslator::fromYaml($path);
    }

    public function testEveryDay()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate($now),
            startTime: new ChronosTime($now),
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('date-interval', $parts);
        $this->assertEquals('dal 01/01/2024', $parts['date-interval']);
        $this->assertArrayNotHasKey('interval', $parts); // DAILY non tradotto qui
    }

    public function testEverySpecificDaysWithTimeRange()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate($now),
            startTime: new ChronosTime($now->setTime(9, 0)),
            endTimeOrDuration: new ChronosTime($now->setTime(11, 0)),
            byDay: [
                DayOfWeek::MONDAY,
                DayOfWeek::WEDNESDAY,
            ]
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();
        $this->assertArrayHasKey('time-interval', $parts);
        $this->assertStringContainsString('dalle', $parts['time-interval']);
        $this->assertArrayHasKey('by-days', $parts);
        $this->assertStringContainsString('lunedì', $parts['by-days']);
        $this->assertStringContainsString('mercoledì', $parts['by-days']);
    }

    public function testEveryDayWithEndDate()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate($now),
            endDate: new ChronosDate($now->modify('+5 days')),
            startTime: new ChronosTime($now->setTime(10, 0)),
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('date-interval', $parts);
        $this->assertStringContainsString('fino al', $parts['date-interval']);
        $this->assertArrayNotHasKey('interval', $parts); // DAILY non aggiunge 'interval'
    }

    public function testOnlyEndDate()
    {
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            endDate: new ChronosDate(new DateTimeImmutable('2024-02-10'))
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('date-interval', $parts);
        $this->assertSame('fino al 10/02/2024', $parts['date-interval']);
    }

    public function testOnlyEndTime()
    {
        $now = new DateTimeImmutable('2024-01-01 18:00:00');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            endTimeOrDuration: new ChronosTime($now)
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('time-interval', $parts);
        $this->assertSame('fino alle 18:00', $parts['time-interval']);
    }

    public function testEveryDayWithRepeatCount()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate($now),
            startTime: new ChronosTime($now),
            repeatCount: 10
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('repeat-count', $parts);
        $this->assertSame('10 occorrenze', $parts['repeat-count']);
    }

    public function testExceptDates()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $except = $now->modify('+2 days');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: new ChronosDate($now),
            startTime: new ChronosTime($now->setTime(8, 30)),
            exceptDates: [new ChronosDate($except)]
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        // La stringa di eccezioni viene aggiunta come elemento indicizzato
        $this->assertTrue(
            in_array('esclusi 03/01/2024', $parts, true) ||
            in_array('esclusi ' . $except->format('d/m/Y'), $parts, true)
        );
    }

    public function testOnlyTimeRange()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: new ChronosDate($now),
            startTime: new ChronosTime($now->setTime(14, 0)),
            endTimeOrDuration: new ChronosTime($now->setTime(18, 0))
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('time-interval', $parts);
        $this->assertStringContainsString('dalle', $parts['time-interval']);
    }

    public function testHumanizeDuration()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: new ChronosDate($now),
            startTime: new ChronosTime($now->setTime(16, 0)),
            endTimeOrDuration: 'PT2H'
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('time-interval', $parts);
        $this->assertStringContainsString('2 ore', $parts['time-interval']);
    }

    public function testByMonthDay()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_MONTH,
            startDate: new ChronosDate($now),
            byMonthDay: [1, 15, 31]
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('by-month-days', $parts);
        $this->assertSame('il 1, 15, 31 del mese', $parts['by-month-days']);
        $this->assertArrayHasKey('interval', $parts);
        $this->assertSame('ogni 30 giorni', $parts['interval']);
    }

    public function testByMonthsOrderedAndTranslated()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_YEAR,
            startDate: new ChronosDate($now),
            byMonth: [
                Month::DECEMBER,
                Month::JANUARY,
                Month::MARCH,
            ]
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('by-months', $parts);
        $this->assertSame('a gennaio, marzo, dicembre', $parts['by-months']);
        $this->assertArrayHasKey('interval', $parts);
        $this->assertSame('ogni 365 giorni', $parts['interval']);
    }

    public function testByMonthWeeksPositiveOrderAndTranslation()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_MONTH,
            startDate: new ChronosDate($now),
            byMonthWeek: [2, 1, 4]
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('by-month-weeks', $parts);
        $this->assertSame('ogni prima e seconda e quarta settimana del mese', $parts['by-month-weeks']);
    }

    public function testByMonthWeeksNegativeOrderAndTranslation()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_MONTH,
            startDate: new ChronosDate($now),
            byMonthWeek: [-1, -3, -2]
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('by-month-weeks', $parts);
        $this->assertSame('ogni terzultima e penultima e ultima settimana del mese', $parts['by-month-weeks']);
    }

    public function testCombinedFiltersDaysMonthsMonthDayMonthWeek()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_MONTH,
            startDate: new ChronosDate($now),
            startTime: new ChronosTime($now->setTime(9, 0)),
            endTimeOrDuration: new ChronosTime($now->setTime(10, 30)),
            byDay: [DayOfWeek::FRIDAY, DayOfWeek::MONDAY],
            byMonthDay: [5, 20],
            byMonth: [
                Month::APRIL,
                Month::FEBRUARY,
            ],
            byMonthWeek: [1, -1]
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('time-interval', $parts);
        $this->assertStringContainsString('dalle', $parts['time-interval']);

        $this->assertArrayHasKey('by-days', $parts);
        $this->assertSame('lunedì e venerdì', $parts['by-days']); // ordinati lun->ven

        $this->assertArrayHasKey('by-month-days', $parts);
        $this->assertSame('il 5, 20 del mese', $parts['by-month-days']);

        $this->assertArrayHasKey('by-months', $parts);
        $this->assertSame('a febbraio, aprile', $parts['by-months']); // ordinati

        $this->assertArrayHasKey('by-month-weeks', $parts);
        $this->assertSame('ogni prima e ultima settimana del mese', $parts['by-month-weeks']);
    }

    public function testWeeklyIntervalIsTranslated()
    {
        $now = new DateTimeImmutable('2024-01-01');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::EVERY_WEEK,
            startDate: new ChronosDate($now)
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('interval', $parts);
        $this->assertSame('ogni 7 giorni', $parts['interval']);
    }

    public function testHumanizeDurationHoursAndMinutesAndDays()
    {
        // 1 giorno, 2 ore, 30 minuti
        $now = new DateTimeImmutable('2024-01-01 10:00:00');
        $schedule = new Schedule(
            repeatInterval: ScheduleInterval::NONE,
            startDate: new ChronosDate($now),
            startTime: new ChronosTime($now),
            endTimeOrDuration: new \DateInterval('P1DT2H30M')
        );

        $humanizer = new ScheduleHumanizer($schedule, $this->getTranslator());
        $parts = $humanizer->humanize();

        $this->assertArrayHasKey('time-interval', $parts);
        $this->assertStringContainsString('1 giorno', $parts['time-interval']);
        $this->assertStringContainsString('2 ore', $parts['time-interval']);
        $this->assertStringContainsString('30 minuti', $parts['time-interval']);
    }
}