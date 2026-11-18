<?php

declare(strict_types=1);

namespace HuitSchedule\Domain;

use InvalidArgumentException;

final class TimeSlot
{
    public readonly int $dayOfWeek;
    public readonly int $startPeriod;
    public readonly int $endPeriod;
    public readonly string $startDate;
    public readonly string $endDate;

    /** @var array<int, array{start: string, end: string, start_min: int, end_min: int, session: string}> */
    private const DEFAULT_PERIODS = [
        1  => ['start' => '07:00', 'end' => '07:50', 'start_min' => 420,  'end_min' => 470,  'session' => 'morning'],
        2  => ['start' => '07:50', 'end' => '08:40', 'start_min' => 470,  'end_min' => 520,  'session' => 'morning'],
        3  => ['start' => '08:40', 'end' => '09:30', 'start_min' => 520,  'end_min' => 570,  'session' => 'morning'],
        4  => ['start' => '09:40', 'end' => '10:30', 'start_min' => 580,  'end_min' => 630,  'session' => 'morning'],
        5  => ['start' => '10:30', 'end' => '11:20', 'start_min' => 630,  'end_min' => 680,  'session' => 'morning'],
        6  => ['start' => '11:20', 'end' => '12:10', 'start_min' => 680,  'end_min' => 730,  'session' => 'morning'],
        7  => ['start' => '12:30', 'end' => '13:20', 'start_min' => 750,  'end_min' => 800,  'session' => 'afternoon'],
        8  => ['start' => '13:20', 'end' => '14:10', 'start_min' => 800,  'end_min' => 850,  'session' => 'afternoon'],
        9  => ['start' => '14:10', 'end' => '15:00', 'start_min' => 850,  'end_min' => 900,  'session' => 'afternoon'],
        10 => ['start' => '15:10', 'end' => '16:00', 'start_min' => 910,  'end_min' => 960,  'session' => 'afternoon'],
        11 => ['start' => '16:00', 'end' => '16:50', 'start_min' => 960,  'end_min' => 1010, 'session' => 'afternoon'],
        12 => ['start' => '16:50', 'end' => '17:40', 'start_min' => 1010, 'end_min' => 1060, 'session' => 'afternoon'],
        13 => ['start' => '18:00', 'end' => '18:50', 'start_min' => 1080, 'end_min' => 1130, 'session' => 'evening'],
        14 => ['start' => '18:50', 'end' => '19:40', 'start_min' => 1130, 'end_min' => 1180, 'session' => 'evening'],
        15 => ['start' => '19:40', 'end' => '20:30', 'start_min' => 1180, 'end_min' => 1230, 'session' => 'evening'],
    ];

    public function __construct(
        int $dayOfWeek,
        int $startPeriod,
        int $endPeriod,
        string $startDate = '2026-01-01',
        string $endDate = '2026-12-31'
    ) {
        if ($dayOfWeek < 2 || $dayOfWeek > 8) {
            throw new InvalidArgumentException('Thứ trong tuần phải từ 2 (Thứ 2) đến 8 (Chủ nhật).');
        }
        if ($startPeriod < 1 || $startPeriod > 15 || $endPeriod < 1 || $endPeriod > 15) {
            throw new InvalidArgumentException('Tiết học phải nằm trong khoảng từ 1 đến 15.');
        }
        if ($endPeriod < $startPeriod) {
            throw new InvalidArgumentException('Tiết kết thúc phải lớn hơn hoặc bằng tiết bắt đầu.');
        }
        if (!self::isValidCalendarDate($startDate) || !self::isValidCalendarDate($endDate)) {
            throw new InvalidArgumentException('Ngày bắt đầu hoặc ngày kết thúc không hợp lệ (YYYY-MM-DD).');
        }
        if ($endDate < $startDate) {
            throw new InvalidArgumentException('Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.');
        }

        $this->dayOfWeek   = $dayOfWeek;
        $this->startPeriod = $startPeriod;
        $this->endPeriod   = $endPeriod;
        $this->startDate   = $startDate;
        $this->endDate     = $endDate;
    }

    private static function isValidCalendarDate(string $date): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['day_of_week'],
            (int) $row['start_period'],
            (int) $row['end_period'],
            (string) ($row['start_date'] ?? '2026-01-01'),
            (string) ($row['end_date'] ?? '2026-12-31')
        );
    }

    /**
     * Hard Overlap algebra:
     * overlaps(A, B) <=> (A.day == B.day) && (A.s <= B.e) && (B.s <= A.e) && (A.startDate <= B.endDate) && (B.startDate <= A.endDate)
     */
    public function overlaps(self $other): bool
    {
        return $this->dayOfWeek === $other->dayOfWeek
            && $this->startPeriod <= $other->endPeriod
            && $other->startPeriod <= $this->endPeriod
            && $this->startDate <= $other->endDate
            && $other->startDate <= $this->endDate;
    }

    public function overlapsDates(self $other): bool
    {
        return $this->startDate <= $other->endDate && $other->startDate <= $this->endDate;
    }

    public function overlapsPeriods(self $other): bool
    {
        return $this->dayOfWeek === $other->dayOfWeek
            && $this->startPeriod <= $other->endPeriod
            && $other->startPeriod <= $this->endPeriod;
    }

    public function containsPeriodRange(int $start, int $end): bool
    {
        return $this->startPeriod <= $start && $this->endPeriod >= $end;
    }

    /**
     * Actual break duration in minutes between two non-overlapping slots on the same day.
     * Returns null if different days or overlapping periods.
     */
    public function breakMinutes(self $other): ?int
    {
        if ($this->dayOfWeek !== $other->dayOfWeek) {
            return null;
        }
        if ($this->startPeriod <= $other->endPeriod && $other->startPeriod <= $this->endPeriod) {
            return null;
        }

        $periods = self::periodMap();
        if ($this->endPeriod < $other->startPeriod) {
            return $periods[$other->startPeriod]['start_min'] - $periods[$this->endPeriod]['end_min'];
        }
        return $periods[$this->startPeriod]['start_min'] - $periods[$other->endPeriod]['end_min'];
    }

    /**
     * Empty period gap between two non-overlapping slots on the same day.
     */
    public function gapPeriods(self $other): ?int
    {
        if ($this->dayOfWeek !== $other->dayOfWeek) {
            return null;
        }
        if ($this->startPeriod <= $other->endPeriod && $other->startPeriod <= $this->endPeriod) {
            return null;
        }
        if ($this->endPeriod < $other->startPeriod) {
            return $other->startPeriod - $this->endPeriod - 1;
        }
        return $this->startPeriod - $other->endPeriod - 1;
    }

    public function periodCount(): int
    {
        return $this->endPeriod - $this->startPeriod + 1;
    }

    public function startMinute(): int
    {
        return self::periodMap()[$this->startPeriod]['start_min'];
    }

    public function endMinute(): int
    {
        return self::periodMap()[$this->endPeriod]['end_min'];
    }

    public function startTime(): string
    {
        return self::periodMap()[$this->startPeriod]['start'];
    }

    public function endTime(): string
    {
        return self::periodMap()[$this->endPeriod]['end'];
    }

    public function timeRange(): string
    {
        return $this->startTime() . ' - ' . $this->endTime();
    }

    public function session(): string
    {
        return self::periodMap()[$this->startPeriod]['session'];
    }

    public function shiftLabel(): string
    {
        $map = [
            '1-3'   => 'Ca 1 (Tiết 1-3)',
            '4-6'   => 'Ca 2 (Tiết 4-6)',
            '7-9'   => 'Ca 3 (Tiết 7-9)',
            '10-12' => 'Ca 4 (Tiết 10-12)',
            '13-15' => 'Ca 5 (Tiết 13-15)',
        ];
        $key = $this->startPeriod . '-' . $this->endPeriod;
        return $map[$key] ?? sprintf('Tiết %d-%d', $this->startPeriod, $this->endPeriod);
    }

    /**
     * @return array<int, array{start: string, end: string, start_min: int, end_min: int, session: string}>
     */
    public static function periodMap(): array
    {
        if (function_exists('app_config')) {
            $configured = app_config('periods');
            if (is_array($configured) && count($configured) === 15) {
                return $configured;
            }
        }
        return self::DEFAULT_PERIODS;
    }
}
