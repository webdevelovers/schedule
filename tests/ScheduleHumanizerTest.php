<?php

namespace WebDevelovers\Schedule\Tests;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Humanizer\HumanizerTranslatorInterface;
use WebDevelovers\Schedule\Humanizer\ScheduleHumanizer;
use WebDevelovers\Schedule\Schedule;

class ScheduleHumanizerTest extends TestCase
{
    private function getTranslator(): HumanizerTranslatorInterface
    {
        $defaultTranslations = [
            'schedule.frequency.none'            => 'Nessuna ripetizione',
            'schedule.frequency.daily'           => 'Giornaliera',
            'schedule.frequency.weekly'          => 'Settimanale',
            'schedule.frequency.every_two_weeks' => 'Ogni due settimane',
            'schedule.frequency.every_three_weeks'=> 'Ogni tre settimane',
            'schedule.frequency.every_four_weeks'=> 'Ogni quattro settimane',
            'schedule.frequency.monthly'         => 'Mensile',
            'schedule.frequency.every_two_months'=> 'Ogni due mesi',
            'schedule.frequency.every_three_months'=> 'Ogni tre mesi',
            'schedule.frequency.every_four_months'=> 'Ogni quattro mesi',
            'schedule.frequency.every_six_months'=> 'Ogni sei mesi',
            'schedule.frequency.yearly'          => 'Annuale',
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
            'schedule.from_date'        => 'dal %date%',
            'schedule.from_to_date'        => 'dal %start% fino al %end%',
            'schedule.every_day'        => 'ogni giorno',
            'schedule.every_days'       => 'ogni %days%',
            'schedule.every_month_days' => 'ogni mese il %days%',
            'schedule.every_x'          => 'ogni %interval%',
            'schedule.interval'         => '%interval%',
            'schedule.day.monday'       => 'lunedì',
            'schedule.day.tuesday'      => 'martedì',
            'schedule.day.wednesday'    => 'mercoledì',
            'schedule.day.thursday'     => 'giovedì',
            'schedule.day.friday'       => 'venerdì',
            'schedule.day.saturday'     => 'sabato',
            'schedule.day.sunday'       => 'domenica',
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
            'schedule.from_to'          => 'dalle %start% alle %end%',
            'schedule.from_duration'    => 'dalle %start% per %duration%',
            'schedule.repeat_count'     => '%count% occorrenze',
            'schedule.until'            => 'fino al %date%',
            'schedule.except'           => 'esclusi %dates%',
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
        $this->assertEquals('dal 01/01/2024, ogni giorno', $humanizer->humanize());
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
        $this->assertStringContainsString('ogni', $humanizer->humanize());
        $this->assertStringContainsString('dalle', $humanizer->humanize());
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
        $result = $humanizer->humanize();
        $this->assertStringContainsString('ogni giorno', $result);
        $this->assertStringContainsString('fino al', $result);
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
        $result = $humanizer->humanize();
        $this->assertStringContainsString('ogni giorno', $result);
        $this->assertStringContainsString('10 occorrenze', $result);
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
        $result = $humanizer->humanize();
        $this->assertStringContainsString('03/01/2024', $result);
        $this->assertStringContainsString($except->format('d/m/Y'), $result);
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
        $result = $humanizer->humanize();
        $this->assertStringContainsString('dalle', $result);
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
        $result = $humanizer->humanize();
        $this->assertStringContainsString('per', $result);
    }
}