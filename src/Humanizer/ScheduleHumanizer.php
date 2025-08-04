<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Humanizer;

use DateInterval;
use DateTimeInterface;
use WebDevelovers\Schedule\Enum\DayOfWeek;
use WebDevelovers\Schedule\Enum\Frequency;
use WebDevelovers\Schedule\Enum\Month;
use WebDevelovers\Schedule\Schedule;

use function array_map;
use function count;
use function implode;
use function sprintf;
use function strtolower;

readonly class ScheduleHumanizer
{
    public function __construct(
        private Schedule $schedule,
        private HumanizerTranslatorInterface $translator,
        private string $locale = 'it',
    ) {
    }

    public function humanize(): string
    {
        $parts = [];

        if ($this->schedule->startDate) {
            $parts[] = $this->translator->trans('schedule.from_date', [
                '%date%' => $this->formatDate($this->schedule->startDate),
            ], domain: 'schedule', locale: $this->locale);
        }

        // Times (start, end)
        $range = $this->humanizeTimeRange();
        if ($range) {
            $parts[] = $range;
        }

        // Repetition count
        if ($this->schedule->repeatCount) {
            $parts[] = $this->translator->trans('schedule.repeat_count', [
                '%count%' => $this->schedule->repeatCount,
            ], domain: 'schedule', locale: $this->locale);
        } elseif ($this->schedule->endDate) {
            $parts[] = $this->translator->trans('schedule.until', [
                '%date%' => $this->formatDate($this->schedule->endDate),
            ], domain: 'schedule', locale: $this->locale);
        }

        // Frequency
        $parts[] = $this->humanizeFrequency();

        // Exclusions
        if (count($this->schedule->exceptDates) > 0) {
            $dates = array_map(fn (DateTimeInterface $d) => $this->formatDate($d), $this->schedule->exceptDates);
            $parts[] = $this->translator->trans('schedule.except', [
                '%dates%' => implode(', ', $dates),
            ], domain: 'schedule', locale: $this->locale);
        }

        return implode(', ', $parts);
    }

    private function humanizeFrequency(): string
    {
        $s = $this->schedule;
        $t = $this->translator;

        if ($s->repeatFrequency === Frequency::NONE) {
            return $this->translator->trans('frequency.none', domain: 'schedule', locale: $this->locale);
        }

        if ($s->repeatFrequency->equals(Frequency::DAILY)) {
            if ($s->byDay && count($s->byDay) > 0) {
                return $t->trans('schedule.every_days', [
                    '%days%' => $this->humanizeDays($s->byDay),
                ], domain: 'schedule', locale: $this->locale);
            }

            return $t->trans('schedule.every_day', [], domain: 'schedule', locale: $this->locale);
        }

        if ($s->repeatFrequency->equals(Frequency::WEEKLY) && $s->byDay && count($s->byDay) > 0) {
            return $t->trans('schedule.every_days', [
                '%days%' => $this->humanizeDays($s->byDay),
            ], domain: 'schedule', locale: $this->locale);
        }

        if ($s->repeatFrequency->equals(Frequency::MONTHLY)) {
            if ($s->byMonthDay && count($s->byMonthDay) > 0) {
                return $t->trans('schedule.every_month_days', [
                    '%days%' => implode(', ', $s->byMonthDay),
                ], domain: 'schedule', locale: $this->locale);
            }

            if ($s->byMonthWeek && count($s->byMonthWeek) > 0 && $s->byDay && count($s->byDay) > 0) {
                $settimane = [];
                foreach ($s->byMonthWeek as $week) {
                    $wk = match ($week) {
                        1 => 'primo',
                        2 => 'secondo',
                        3 => 'terzo',
                        4 => 'quarto',
                        -1 => 'ultimo',
                        default => $week . '°'
                    };
                    $settimane[] = $wk;
                }

                $descSettimane = implode(' e ', $settimane);

                return sprintf(
                    'ogni %s %s del mese',
                    $descSettimane,
                    $this->humanizeDays($s->byDay),
                );
            }
        }

        if ($s->byMonth && count($s->byMonth) > 0) {
            $mesi = array_map(static fn (Month $m) => strtolower($m->name), $s->byMonth);

            return $t->trans('schedule.every_x', [
                '%interval%' => implode(', ', $mesi),
            ], domain: 'schedule', locale: $this->locale);
        }

        return $t->trans('schedule.every_x', [
            '%interval%' => $this->humanizeInterval($s->repeatFrequency),
        ], domain: 'schedule', locale: $this->locale);
    }

    /** @param DayOfWeek[] $days */
    private function humanizeDays(array $days): string
    {
        return implode(' e ', array_map(
            fn (DayOfWeek $d) => $this->translator->trans('schedule.day.' . strtolower($d->name), [], 'schedule', $this->locale),
            $days,
        ));
    }

    private function humanizeTimeRange(): string|null
    {
        $s = $this->schedule;

        if (! $s->startTime) {
            return null;
        }

        if ($s->duration) {
            $interval = new DateInterval($s->duration);
            $durationStr = $this->humanizeDuration($interval);

            return $this->translator->trans(
                'schedule.from_duration',
                [
                    '%start%' => $this->formatTime($s->startTime),
                    '%duration%' => $durationStr,
                ],
                domain: 'schedule',
                locale: $this->locale,
            );
        }

        if ($s->endTime) {
            return $this->translator->trans(
                'schedule.from_to',
                [
                    '%start%' => $this->formatTime($s->startTime),
                    '%end%' => $this->formatTime($s->endTime),
                ],
                domain: 'schedule',
                locale: $this->locale,
            );
        }

        return null;
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
            $string = $interval->h > 1 ? 'schedule.interval.days' : 'schedule.interval.day';
            $parts[] = $this->translator->trans(
                $string,
                ['%count%' => $interval->d],
                'schedule',
                $this->locale,
            );
        }

        if ($interval->m > 0) {
            $string = $interval->h > 1 ? 'schedule.interval.months' : 'schedule.interval.month';
            $parts[] = $this->translator->trans(
                $string,
                ['%count%' => $interval->m],
                'schedule',
                $this->locale,
            );
        }

        if ($interval->y > 0) {
            $string = $interval->h > 1 ? 'schedule.interval.years' : 'schedule.interval.year';
            $parts[] = $this->translator->trans(
                $string,
                ['%count%' => $interval->y],
                'schedule',
                $this->locale,
            );
        }

        return implode(' ', $parts);
    }

    private function formatDate(DateTimeInterface $date): string
    {
        return $date->format('d/m/Y');
    }

    private function formatTime(DateTimeInterface $date): string
    {
        return $date->format('H:i');
    }

    private function humanizeInterval(Frequency $frequency): string
    {
        return match ($frequency) {
            Frequency::YEARLY  => $this->translator->trans('schedule.interval.years', ['%count%' => 1], domain: 'schedule', locale: $this->locale),
            Frequency::MONTHLY => $this->translator->trans('schedule.interval.months', ['%count%' => 1], domain: 'schedule', locale: $this->locale),
            Frequency::WEEKLY  => $this->translator->trans('schedule.interval.days', ['%count%' => 7], domain: 'schedule', locale: $this->locale),
            Frequency::DAILY   => $this->translator->trans('schedule.interval.days', ['%count%' => 1], domain: 'schedule', locale: $this->locale),
            default => $frequency->value,
        };
    }
}
