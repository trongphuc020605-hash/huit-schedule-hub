<?php

declare(strict_types=1);

namespace HuitSchedule\Core;

final class Response
{
    public static function html(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo $content;
    }

    public static function redirect(string $path, int $status = 302): void
    {
        $target = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : url($path);
        http_response_code($status);
        header('Location: ' . $target);
        exit;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function jsonSuccess(mixed $data, array $meta = [], int $status = 200): void
    {
        $envelope = [
            'ok'   => true,
            'data' => $data,
        ];
        if ($meta !== []) {
            $envelope['meta'] = $meta;
        }
        self::json($envelope, $status);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function jsonError(string $message, int $status = 400, array $details = []): void
    {
        $envelope = [
            'ok'    => false,
            'error' => $message,
            'code'  => $status,
        ];
        if ($details !== []) {
            $envelope['details'] = $details;
        }
        self::json($envelope, $status);
    }

    public static function icsDownload(string $icsContent, string $filename): void
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?: 'huit-schedule.ics';
        http_response_code(200);
        header('Content-Type: text/calendar; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . strlen($icsContent));
        echo $icsContent;
        exit;
    }

    /**
     * Stream a UTF-8 BOM CSV file that opens cleanly with Vietnamese accents in Microsoft Excel.
     *
     * @param string[] $headers
     * @param array<int, array<int, string|int|float|null>> $rows
     */
    public static function csvDownload(array $headers, array $rows, string $filename): void
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?: 'huit-schedules.csv';
        http_response_code(200);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'wb');
        if ($out !== false) {
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }
        exit;
    }
}
