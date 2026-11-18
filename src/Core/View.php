<?php

declare(strict_types=1);

namespace HuitSchedule\Core;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $content = self::renderToString($template, $data, $layout);
        Response::html($content);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function renderToString(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $viewFile = base_path('views/' . ltrim($template, '/') . '.php');
        if (!is_file($viewFile)) {
            throw new RuntimeException('View không tồn tại: ' . $template);
        }

        $content = self::capture($viewFile, $data);

        if ($layout === null) {
            return $content;
        }

        $layoutFile = base_path('views/' . ltrim($layout, '/') . '.php');
        if (!is_file($layoutFile)) {
            throw new RuntimeException('Layout không tồn tại: ' . $layout);
        }

        $layoutData = array_merge($data, [
            'content' => $content,
            'flashes' => Flash::consume(),
        ]);

        return self::capture($layoutFile, $layoutData);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function partial(string $partial, array $data = []): void
    {
        $file = base_path('views/' . ltrim($partial, '/') . '.php');
        if (!is_file($file)) {
            throw new RuntimeException('Partial không tồn tại: ' . $partial);
        }
        extract($data, EXTR_SKIP);
        require $file;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
