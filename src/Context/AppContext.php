<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Context;

use Illuminate\Support\Facades\Context;
use Throwable;

class AppContext
{
    /**
     * Пустые блоки в результат не попадают.
     *
     * @return array{shared?: array, exception?: array}
     */
    public static function capture(Throwable $exception): array
    {
        $blocks = [
            'shared'    => self::shared(),
            'exception' => self::fromException($exception),
        ];

        $result = [];
        foreach ($blocks as $name => $data) {
            if ($data !== []) {
                $result[$name] = self::prepare($data);
            }
        }

        return $result;
    }

    protected static function shared(): array
    {
        try {
            // Фасад Context появился в Laravel 11; на 10.x блок просто пропускается
            return class_exists(Context::class) ? Context::all() : [];
        } catch (Throwable) {
            return [];
        }
    }

    protected static function fromException(Throwable $exception): array
    {
        try {
            $data = method_exists($exception, 'context') ? $exception->context() : [];

            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }

    protected static function prepare(array $data): array
    {
        $scrubFields = function_exists('config') ? (array)config('larasentry.scrub_fields', []) : [];
        $data = Sanitizer::clean($data, $scrubFields);

        // Сервер ждёт JSON-объект: список ушёл бы массивом, и сервер отклонил бы всё событие при разборе
        if (array_is_list($data)) {
            $data = ['value' => $data];
        }

        // Без этой проверки один битый UTF-8 в значении ломает json_encode всего payload, и событие теряется
        $json = json_encode($data);
        if ($json === false) {
            return ['_invalid' => json_last_error_msg()];
        }

        $maxBytes = function_exists('config') ? (int)config('larasentry.max_context_bytes', 16384) : 16384;
        if (strlen($json) > $maxBytes) {
            return ['_truncated' => true, '_size' => strlen($json)];
        }

        return $data;
    }
}
