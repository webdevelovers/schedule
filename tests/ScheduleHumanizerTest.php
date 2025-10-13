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
        $defaultTranslations = [
            // DayOfWeeks
            'schedule.day.monday'       => 'lunedì',
            'schedule.day.tuesday'      => 'martedì',
            'schedule.day.wednesday'    => 'mercoledì',
            'schedule.day.thursday'     => 'giovedì',
            'schedule.day.friday'       => 'venerdì',
            'schedule.day.saturday'     => 'sabato',
            'schedule.day.sunday'       => 'domenica',

            // Months
            'schedule.month.january'             => 'Gennaio',
            'schedule.month.february'            => 'Febbraio',
            'schedule.month.march'               => 'Marzo',
            'schedule.month.april'               => 'Aprile',
            'schedule.month.may'                 => 'Maggio',
            'schedule.month.june'                => 'Giugno',
            'schedule.month.july'                => 'Luglio',
            'schedule.month.august'              => 'Agosto',
            'schedule.month.september'           => 'Settembre',
            'schedule.month.october'             => 'Ottobre',
            'schedule.month.november'            => 'Novembre',
            'schedule.month.december'            => 'Dicembre',

            // Start/End Date, Time, Repeat Count
            'schedule.from_date'                 => 'dal %start%',
            'schedule.from_to_date'              => 'dal %start% fino al %end%',
            'schedule.to_date'                   => 'fino al %end%',
            'schedule.from_to_time'              => 'dalle %start% alle %end% (%duration%)',
            'schedule.from_time'                 => 'dalle %start%',
            'schedule.to_time'                   => 'fino alle %end%',
            'schedule.repeat_count'              => '%count% occorrenze',

            // Interval
            'schedule.interval.year'    => '%count% anno',
            'schedule.interval.years'   => '%count% anni',
            'schedule.interval.month'   => '%count% mese',
            'schedule.interval.months'  => '%count% mesi',
            'schedule.interval.day'     => '%count% giorno',
            'schedule.interval.days'    => '%count% giorni',
            'schedule.interval.hour'    => '%count% ora',
            'schedule.interval.hours'   => '%count% ore',
            'schedule.interval.minute'  => '%count% minuto',
            'schedule.interval.minutes' => '%count% minuti',

            // Frequency
            'schedule.frequency.none'               => 'nessuna ripetizione',
            'schedule.frequency.daily'              => 'ripetizione giornaliera',
            'schedule.frequency.every_week'         => 'ogni 7 giorni',
            'schedule.frequency.every_two_weeks'    => 'ogni due settimane',
            'schedule.frequency.every_three_weeks'  => 'ogni tre settimane',
            'schedule.frequency.every_four_weeks'   => 'ogni quattro settimane',
            'schedule.frequency.every_month'        => 'ogni 30 giorni',
            'schedule.frequency.every_two_months'   => 'ogni due mesi',
            'schedule.frequency.every_three_months' => 'ogni tre mesi',
            'schedule.frequency.every_four_months'  => 'ogni quattro mesi',
            'schedule.frequency.every_six_months'   => 'ogni sei mesi',
            'schedule.frequency.every_year'         => 'ogni 365 giorni',

            // MonthWeek
            'schedule.month_week.first'             => 'prima',
            'schedule.month_week.second'            => 'seconda',
            'schedule.month_week.third'             => 'terza',
            'schedule.month_week.fourth'            => 'quarta',
            'schedule.month_week.fifth'             => 'quinta',
            'schedule.month_week.sixth'             => 'sesta',
            'schedule.month_week.last'              => 'ultima',
            'schedule.month_week.second_to_last'    => 'penultima',
            'schedule.month_week.third_to_last'     => 'terzultima',
            'schedule.month_week.fourth_to_last'    => 'quartultima',
            'schedule.month_week.fifth_to_last'     => 'quintultima',
            'schedule.month_week.sixth_to_last'     => 'sestultima',

            // Filters
            'schedule.every_month_days'         => 'il %days% del mese',
            'schedule.every_months'             => 'a %months%',
            'schedule.every_month_weeks'        => 'ogni %month_weeks% settimana del mese',
            'schedule.except'                   => 'esclusi %dates%',
        ];

        return new ArrayTranslator($defaultTranslations);
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
        $this->assertSame('a Gennaio, Marzo, Dicembre', $parts['by-months']);
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
        $this->assertSame('a Febbraio, Aprile', $parts['by-months']); // ordinati

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