<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Humanizer;

use Cake\Chronos\ChronosDate;
use DateInterval;
use DateTimeInterface;
use InvalidArgumentException;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Enum\ScheduleInterval;
use WebDevelovers\Schedule\Schedule;

use function array_map;
use function assert;
use function count;
use function implode;
use function strtolower;
use function usort;

/** @deprecated - use Schedule->toArray instead */
readonly class ScheduleHumanizer
{
    public function __construct(
        private Schedule $schedule,
        private HumanizerTranslatorInterface $translator,
        private string $locale = 'it',
    ) {
    }

    /** @return array<string,string> */
    public function humanize(): array
    {
        $parts = [];

        $this->humanizeFromToDate($parts);
        $this->humanizeFromToTime($parts);
        $this->humanizeRepeatCount($parts);
        $this->humanizeInterval($parts);
        $this->humanizeFilters($parts);

        // Exclusions
        if (count($this->schedule->exceptDates) > 0) {
            $dates = array_map(fn (ChronosDate $d) => $this->formatDate($d), $this->schedule->exceptDates);
            $parts['except-dates'] = $this->translator->trans('schedule.except', [
                '%dates%' => implode(', ', $dates),
            ], domain: 'schedule', locale: $this->locale);
        }

        return $parts;
    }

    public function humanizeAsString(): string
    {
        return implode(' | ', $this->humanize());
    }

    /** @param array<string,string> $parts */
    private function humanizeFromToDate(array &$parts): void
    {
        if ($this->schedule->startDate) {
            if ($this->schedule->endDate) {
                $parts['date-interval'] = $this->translator->trans('schedule.from_to_date', [
                    '%start%' => $this->formatDate($this->schedule->startDate->toDateTimeImmutable()),
                    '%end%' => $this->formatDate($this->schedule->endDate->toDateTimeImmutable()),
                ], domain: 'schedule', locale: $this->locale);
            } else {
                $parts['date-interval'] = $this->translator->trans('schedule.from_date', [
                    '%start%' => $this->formatDate($this->schedule->startDate->toDateTimeImmutable()),
                ], domain: 'schedule', locale: $this->locale);
            }
        } else {
            if ($this->schedule->endDate) {
                $parts['date-interval'] = $this->translator->trans('schedule.to_date', [
                    '%end%' => $this->formatDate($this->schedule->endDate->toDateTimeImmutable()),
                ], domain: 'schedule', locale: $this->locale);
            }
        }
    }

    /** @param array<string,string> $parts */
    private function humanizeFromToTime(array &$parts): void
    {
        $schedule = $this->schedule;

        if ($schedule->startTime) {
            if ($schedule->endTime) {
                $interval = $schedule->duration;
                assert($interval instanceof DateInterval);

                $durationStr = $this->humanizeDuration($interval);

                $parts['time-interval'] = $this->translator->trans(
                    'schedule.from_to_time',
                    [
                        '%start%' => $this->formatTime($schedule->startTime->toDateTimeImmutable()),
                        '%end%' => $this->formatTime($schedule->endTime->toDateTimeImmutable()),
                        '%duration%' => $durationStr,
                    ],
                    domain: 'schedule',
                    locale: $this->locale,
                );
            } else {
                $parts['time-interval'] = $this->translator->trans(
                    'schedule.from_time',
                    [
                        '%start%' => $this->formatTime($schedule->startTime->toDateTimeImmutable()),
                    ],
                    domain: 'schedule',
                    locale: $this->locale,
                );
            }
        } else {
            if ($schedule->endTime) {
                $parts['time-interval'] = $this->translator->trans(
                    'schedule.to_time',
                    [
                        '%end%' => $this->formatTime($schedule->endTime->toDateTimeImmutable()),
                    ],
                    domain: 'schedule',
                    locale: $this->locale,
                );
            }
        }
    }

    private function humanizeDuration(DateInterval $interval): string
    {
        $parts = [];

        if ($interval->h > 0) {
            $string = $interval->h > 1 ? 'schedule.interval.hours' : 'schedule.interval.hour';
            $parts[] = $this->translator->trans(
                $string,
                ['%count%' => $interval->h],
                'schedule',
                $this->locale,
            );
        }

        if ($interval->i > 0) {
            $string = $interval->h > 1 ? 'schedule.interval.minutes' : 'schedule.interval.minute';
            $parts[] = $this->translator->trans(
                $string,
                ['%count%' => $interval->i],
                'schedule',
                $this->locale,
            );
        }

        if ($interval->d > 0) {
            $string = $interval->d > 1 ? 'schedule.interval.days' : 'schedule.interval.day';
            $parts[] = $this->translator->trans(
                $string,
                ['%count%' => $interval->d],
                'schedule',
                $this->locale,
            );
        }

        if ($interval->m > 0) {
            $string = $interval->m > 1 ? 'schedule.interval.months' : 'schedule.interval.month';
            $parts[] = $this->translator->trans(
                $string,
                ['%count%' => $interval->m],
                'schedule',
                $this->locale,
            );
        }

        if ($interval->y > 0) {
            $string = $interval->y > 1 ? 'schedule.interval.years' : 'schedule.interval.year';
            $parts[] = $this->translator->trans(
                $string,
                ['%count%' => $interval->y],
                'schedule',
                $this->locale,
            );
        }

        return implode(' ', $parts);
    }

    /** @param array<string,string> $parts */
    private function humanizeRepeatCount(array &$parts): void
    {
        if (! $this->schedule->repeatCount) {
            return;
        }

        $parts['repeat-count'] = $this->translator->trans('schedule.repeat_count', [
            '%count%' => $this->schedule->repeatCount,
        ], domain: 'schedule', locale: $this->locale);
    }

    /** @param array<string,string> $parts */
    private function humanizeInterval(array &$parts): void
    {
        $schedule = $this->schedule;
        $interval = $schedule->repeatInterval;
        if ($interval === ScheduleInterval::DAILY) {
            return;
        }

        $parts['interval'] = $this->translator->trans(
            $interval->label(),
            domain: 'schedule',
            locale: $this->locale,
        );
    }

    /** @param array<string,string> $parts */
    private function humanizeFilters(array &$parts): void
    {
        $schedule = $this->schedule;
        $byDay = $schedule->byDay;
        $byMonthDay = $schedule->byMonthDay;
        $byMonth = $schedule->byMonth;
        $byMonthWeek = $schedule->byMonthWeek;

        if (count($byDay) > 0) {
            $parts['by-days'] = $this->humanizeDays($byDay);
        }

        if (count($byMonthDay) > 0) {
            $parts['by-month-days'] = $this->humanizeMonthDays($byMonthDay);
        }

        if (count($byMonth) > 0) {
            $parts['by-months'] = $this->humanizeMonths($byMonth);
        }

        if (count($byMonthWeek) <= 0) {
            return;
        }

        $parts['by-month-weeks'] = $this->humanizeMonthWeeks($byMonthWeek);
    }

    /** @param DayOfWeek[] $days */
    private function humanizeDays(array $days): string
    {
        $order = [
            DayOfWeek::MONDAY->value    => 1,
            DayOfWeek::TUESDAY->value   => 2,
            DayOfWeek::WEDNESDAY->value => 3,
            DayOfWeek::THURSDAY->value  => 4,
            DayOfWeek::FRIDAY->value    => 5,
            DayOfWeek::SATURDAY->value  => 6,
            DayOfWeek::SUNDAY->value    => 7,
        ];

        usort($days, static function (DayOfWeek $a, DayOfWeek $b) use ($order): int {
            return $order[$a->value] <=> $order[$b->value];
        });

        return implode(' e ', array_map(
            fn (DayOfWeek $d) => $this->translator->trans('schedule.day.' . strtolower($d->name), [], 'schedule', $this->locale),
            $days,
        ));
    }

    /** @param int[] $monthDays */
    private function humanizeMonthDays(array $monthDays): string
    {
        return $this->translator->trans(
            'schedule.every_month_days',
            [
                '%days%' => implode(', ', $monthDays),
            ],
            'schedule',
            $this->locale,
        );
    }

    /** @param Month[] $months */
    private function humanizeMonths(array $months): string
    {
        $order = [
            Month::JANUARY->value => 1,
            Month::FEBRUARY->value => 2,
            Month::MARCH->value => 3,
            Month::APRIL->value => 4,
            Month::MAY->value => 5,
            Month::JUNE->value => 6,
            Month::JULY->value => 7,
            Month::AUGUST->value => 8,
            Month::SEPTEMBER->value => 9,
            Month::OCTOBER->value => 10,
            Month::NOVEMBER->value => 11,
            Month::DECEMBER->value => 12,
        ];
        usort($months, static function (Month $a, Month $b) use ($order): int {
            return $order[$a->value] <=> $order[$b->value];
        });

        $labels = array_map(function (Month $m): string {
            return $this->translator->trans($m->label(), [], 'schedule', $this->locale);
        }, $months);

        return $this->translator->trans(
            'schedule.every_months',
            ['%months%' => implode(', ', $labels)],
            'schedule',
            $this->locale,
        );
    }

    /** @param int[] $monthWeeks */
    private function humanizeMonthWeeks(array $monthWeeks): string
    {
        usort($monthWeeks, static function (int $a, int $b): int {
            if ($a >= 0 && $b < 0) {
                return -1;
            }

            if ($a < 0 && $b >= 0) {
                return 1;
            }

            if ($a >= 0 && $b >= 0) {
                return $a <=> $b;
            }

            return $a <=> $b;
        });

        $labels = array_map(function (int $w): string {
            $key = $this->monthWeekToLabel($w);

            return $this->translator->trans($key, [], 'schedule', $this->locale);
        }, $monthWeeks);

        $monthWeekStr = implode(' e ', $labels);

        return $this->translator->trans(
            'schedule.every_month_weeks',
            ['%month_weeks%' => $monthWeekStr],
            'schedule',
            $this->locale,
        );
    }

    private function monthWeekToLabel(int $monthWeek): string
    {
        return match ($monthWeek) {
            1 => 'schedule.month_week.first',
            2 => 'schedule.month_week.second',
            3 => 'schedule.month_week.third',
            4 => 'schedule.month_week.fourth',
            5 => 'schedule.month_week.fifth',
            6 => 'schedule.month_week.sixth',
            -1 => 'schedule.month_week.last',
            -2 => 'schedule.month_week.second_to_last',
            -3 => 'schedule.month_week.third_to_last',
            -4 => 'schedule.month_week.fourth_to_last',
            -5 => 'schedule.month_week.fifth_to_last',
            -6 => 'schedule.month_week.sixth_to_last',
            default => throw new InvalidArgumentException('Invalid month week: ' . $monthWeek),
        };
    }

    private function formatDate(DateTimeInterface|ChronosDate $date): string
    {
        return $date->format('d/m/Y');
    }

    private function formatTime(DateTimeInterface $date): string
    {
        return $date->format('H:i');
    }
}
