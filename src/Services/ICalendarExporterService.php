<?php

declare(strict_types=1);

namespace HuitSchedule\Services;

use DateTimeImmutable;
use DateTimeZone;
use HuitSchedule\Domain\TimeSlot;

final class ICalendarExporterService
{
    private const TZ = 'Asia/Ho_Chi_Minh';

    /**
     * Generate RFC 5545 compliant .ics calendar content for a teacher's schedules.
     *
     * @param array<string, mixed> $teacher
     * @param array<int, array<string, mixed>> $schedules
     */
    public function export(array $teacher, array $schedules): string
    {
        $tz = new DateTimeZone(self::TZ);
        $nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');
        $calName = 'Lịch giảng dạy HUIT - ' . (string) ($teacher['full_name'] ?? 'Giảng viên');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//HUIT Schedule Hub//Multi-Campus Teaching Scheduler 1.0//VI',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($calName),
            'X-WR-TIMEZONE:' . self::TZ,
            'BEGIN:VTIMEZONE',
            'TZID:' . self::TZ,
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:+0700',
            'TZOFFSETTO:+0700',
            'TZNAME:+07',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];

        foreach ($schedules as $s) {
            $slot = TimeSlot::fromArray($s);
            $firstDate = $this->firstOccurrenceDate((string) $s['start_date'], (int) $s['day_of_week'], $tz);
            $endDateObj = new DateTimeImmutable((string) $s['end_date'] . ' 23:59:59', $tz);
            if ($firstDate > $endDateObj) {
                continue;
            }

            $startHm = str_replace(':', '', $slot->startTime()) . '00';
            $endHm   = str_replace(':', '', $slot->endTime()) . '00';
            $dtStart = $firstDate->format('Ymd') . 'T' . $startHm;
            $dtEnd   = $firstDate->format('Ymd') . 'T' . $endHm;
            $untilUtc = $endDateObj->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');

            $uid = sprintf('huit-sched-%d-%d@huit-schedule.edu.vn', (int) ($s['id'] ?? 0), (int) ($teacher['id'] ?? 0));
            $summary = sprintf(
                '%s (%s - %s)',
                (string) $s['subject_name'],
                (string) ($s['branch_code'] ?? 'HUIT'),
                (string) $s['room']
            );
            $location = sprintf(
                '%s - %s - Phòng %s',
                (string) ($s['branch_name'] ?? ''),
                (string) ($s['branch_address'] ?? ''),
                (string) $s['room']
            );
            $description = sprintf(
                "Giảng viên: %s\nMôn học: %s\nCa/Tiết: %s (%s)\nCơ sở: %s (%s)\nPhòng: %s%s",
                (string) ($teacher['full_name'] ?? ''),
                (string) $s['subject_name'],
                (string) $s['shift_or_periods'],
                $slot->timeRange(),
                (string) ($s['branch_name'] ?? ''),
                (string) ($s['branch_address'] ?? ''),
                (string) $s['room'],
                !empty($s['notes']) ? ("\nGhi chú: " . (string) $s['notes']) : ''
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . $nowUtc;
            $lines[] = 'DTSTART;TZID=' . self::TZ . ':' . $dtStart;
            $lines[] = 'DTEND;TZID=' . self::TZ . ':' . $dtEnd;
            $lines[] = 'RRULE:FREQ=WEEKLY;UNTIL=' . $untilUtc;
            $lines[] = 'SUMMARY:' . $this->escapeText($summary);
            $lines[] = 'LOCATION:' . $this->escapeText($location);
            $lines[] = 'DESCRIPTION:' . $this->escapeText($description);
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Find the first calendar date >= startDate matching HUIT day_of_week (2=Mon..7=Sat, 8=Sun).
     */
    private function firstOccurrenceDate(string $startDate, int $huitDayOfWeek, DateTimeZone $tz): DateTimeImmutable
    {
        $date = new DateTimeImmutable($startDate . ' 00:00:00', $tz);
        // ISO-8601 N: 1=Mon..7=Sun => HUIT day = N + 1
        $targetIso = $huitDayOfWeek - 1;
        $currentIso = (int) $date->format('N');
        $diff = ($targetIso - $currentIso + 7) % 7;
        if ($diff > 0) {
            $date = $date->modify('+' . $diff . ' days');
        }
        return $date;
    }

    private function escapeText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(["\r\n", "\r", "\n"], '\n', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace(';', '\;', $text);
        return $text;
    }
}
