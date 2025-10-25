<?php

namespace WebDevelovers\Schedule\Tests;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosTime;
use PHPUnit\Framework\TestCase;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Holiday\HolidayProviderInterface;
use WebDevelovers\Schedule\Schedule;
use WebDevelovers\Schedule\ScheduleAggregate;
use WebDevelovers\Schedule\ScheduleMetrics;

class ScheduleMetricsTest extends TestCase
{
    private function makeDaily(
        string $startDate,
        ?string $endDate,
        string $startTime,
        string $endTime,
        ?int $repeatCount = null,
        array $byDay = [],
        array $exceptDates = [],
        ScheduleInterval $interval = ScheduleInterval::DAILY,
    ): Schedule {
        return new Schedule(
            repeatInterval: $interval,
            startDate: new ChronosDate($startDate),
            endDate: $endDate ? new ChronosDate($endDate) : null,
            startTime: new ChronosTime($startTime),
            endTimeOrDuration: new ChronosTime($endTime),
            repeatCount: $repeatCount,
            byDay: $byDay,
            exceptDates: array_map(fn (string $d) => new ChronosDate($d), $exceptDates),
            timezone: 'UTC',
        );
    }

    private function holidayProvider(callable $fn): HolidayProviderInterface
    {
        return new class($fn) implements HolidayProviderInterface {
            public function __construct(private $fn) {}
            public function isHoliday(ChronosDate $date): bool
            {
                return (bool) call_user_func($this->fn, $date);
            }
        };
    }

    public function testSecondsForSingleNonRecurringDay2Hours(): void
    {
        $s = $this->makeDaily('2025-01-01', '2025-01-01', '09:00:00', '11:00:00', repeatCount: 1);
        $hp = $this->holidayProvider(fn () => false);

        $seconds = ScheduleMetrics::seconds($s, $hp);
        $this->assertSame(2 * 3600, $seconds);

        $this->assertSame(120.0, ScheduleMetrics::minutes($s, $hp));
        $this->assertSame(2.0, ScheduleMetrics::hours($s, $hp));
        $this->assertSame((2 * 3600) / 86400, ScheduleMetrics::days($s, $hp));
    }

    public function testDailyRecurringThreeDaysTwoHoursEach(): void
    {
        $s = $this->makeDaily('2025-01-01', '2025-01-03', '09:00:00', '11:00:00');
        $hp = $this->holidayProvider(fn () => false);

        $seconds = ScheduleMetrics::seconds($s, $hp);
        $this->assertSame(3 * 2 * 3600, $seconds);
    }

    public function testAggregateSumOfTwoSchedules(): void
    {
        $s1 = $this->makeDaily('2025-01-01', '2025-01-02', '09:00:00', '10:00:00'); // 2 giorni x 1h = 7200
        $s2 = $this->makeDaily('2025-01-01', '2025-01-01', '10:00:00', '12:00:00', repeatCount: 1); // 2h = 7200
        $agg = new ScheduleAggregate([$s1, $s2]);
        $hp = $this->holidayProvider(fn () => false);

        $seconds = ScheduleMetrics::seconds($agg, $hp);
        $this->assertSame(7200 + 7200, $seconds);
    }

    public function testRangeFilterCutsOccurrencesAtBounds(): void
    {
        $s = $this->makeDaily('2025-01-01', '2025-01-03', '09:00:00', '11:00:00');
        $hp = $this->holidayProvider(fn () => false);

        // Considera solo il 2 gennaio
        $from = new \DateTimeImmutable('2025-01-02 00:00:00 UTC');
        $to   = new \DateTimeImmutable('2025-01-02 23:59:59 UTC');

        $seconds = ScheduleMetrics::seconds($s, $hp, $from, $to);
        $this->assertSame(2 * 3600, $seconds);
    }

    public function testRangeBeforeAllOccurrencesReturnsZero(): void
    {
        $s = $this->makeDaily('2025-01-10', '2025-01-10', '09:00:00', '11:00:00', repeatCount: 1);
        $hp = $this->holidayProvider(fn () => false);

        $from = new \DateTimeImmutable('2025-01-01 00:00:00 UTC');
        $to   = new \DateTimeImmutable('2025-01-01 23:59:59 UTC');

        $seconds = ScheduleMetrics::seconds($s, $hp, $from, $to);
        $this->assertSame(0, $seconds);
    }

    public function testRangeAfterAllOccurrencesReturnsZero(): void
    {
        $s = $this->makeDaily('2025-01-01', '2025-01-01', '09:00:00', '11:00:00', repeatCount: 1);
        $hp = $this->holidayProvider(fn () => false);

        $from = new \DateTimeImmutable('2025-02-01 00:00:00 UTC');
        $to   = new \DateTimeImmutable('2025-02-02 00:00:00 UTC');

        $seconds = ScheduleMetrics::seconds($s, $hp, $from, $to);
        $this->assertSame(0, $seconds);
    }

    public function testByDayFilterCountsOnlySelectedWeekdays(): void
    {
        // Dal 1 al 7 gennaio 2025: contiamo solo Lunedì e Mercoledì (2 giorni)
        $s = $this->makeDaily(
            startDate: '2025-01-01',
            endDate: '2025-01-07',
            startTime: '09:00:00',
            endTime: '10:00:00',
            byDay: [DayOfWeek::MONDAY, DayOfWeek::WEDNESDAY]
        );
        $hp = $this->holidayProvider(fn () => false);

        $seconds = ScheduleMetrics::seconds($s, $hp);
        $this->assertSame(2 * 3600, $seconds);
    }

    public function testExceptDatesAreExcluded(): void
    {
        // 1-3 gennaio, 1h al giorno, escludi il 2025-01-02
        $s = $this->makeDaily(
            startDate: '2025-01-01',
            endDate: '2025-01-03',
            startTime: '09:00:00',
            endTime: '10:00:00',
            exceptDates: ['2025-01-02']
        );
        $hp = $this->holidayProvider(fn () => false);

        $seconds = ScheduleMetrics::seconds($s, $hp);
        $this->assertSame(2 * 3600, $seconds);
    }

    public function testHolidayProviderDoesNotAffectDurationButIsSupported(): void
    {
        // Marca festivo il 2025-01-02, ma la durata resta uguale
        $s = $this->makeDaily('2025-01-01', '2025-01-03', '09:00:00', '10:00:00');
        $hp = $this->holidayProvider(fn (ChronosDate $d) => $d->toDateString() === '2025-01-02');

        $seconds = ScheduleMetrics::seconds($s, $hp);
        $this->assertSame(3 * 3600, $seconds);
    }

    public function testMinutesHoursDaysDeriveFromSeconds(): void
    {
        $s = $this->makeDaily('2025-01-01', '2025-01-01', '09:00:00', '11:00:00', repeatCount: 1);
        $hp = $this->holidayProvider(fn () => false);

        $this->assertSame(120.0, ScheduleMetrics::minutes($s, $hp));
        $this->assertSame(2.0, ScheduleMetrics::hours($s, $hp));
        $this->assertSame((2 * 3600) / 86400, ScheduleMetrics::days($s, $hp));
    }

    public function testZeroOccurrencesReturnsZeroAcrossAllMetrics(): void
    {
        // Nessuna espansione possibile (manca startDate)
        $s = new Schedule(
            repeatInterval: ScheduleInterval::DAILY,
            startDate: null,
            startTime: new ChronosTime('09:00:00'),
            endTimeOrDuration: new ChronosTime('10:00:00'),
            timezone: 'UTC',
        );
        $hp = $this->holidayProvider(fn () => false);

        $this->assertSame(0, ScheduleMetrics::seconds($s, $hp));
        $this->assertSame(0.0, ScheduleMetrics::minutes($s, $hp));
        $this->assertSame(0.0, ScheduleMetrics::hours($s, $hp));
        $this->assertSame(0.0, ScheduleMetrics::days($s, $hp));
    }
}