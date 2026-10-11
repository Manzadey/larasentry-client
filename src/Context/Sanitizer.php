<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Context;

class Sanitizer
{
    protected static array $defaultPatterns = [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'api_key',
        'authorization',
        'cookie',
        'card',
        'card_number',
        'cvv',
        'cvc',
    ];

    /**
     * Рекурсивно очищает массив от конфиденциальных данных (PII).
     */
    public static function clean(mixed $data, array $customKeys = []): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $patterns = array_unique(array_merge(self::$defaultPatterns, $customKeys));
        $regex = '/(' . implode('|', array_map('preg_quote', $patterns)) . ')/i';

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match($regex, $key)) {
                $sanitized[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::clean($value, $customKeys);
            } elseif (is_object($value)) {
                if (method_exists($value, 'getKey')) {
                    $sanitized[$key] = (string)$value->getKey();
                } elseif (method_exists($value, '__toString')) {
                    $sanitized[$key] = (string)$value;
                } else {
                    $sanitized[$key] = get_class($value);
                }
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Очищает HTTP-заголовки от токенов сессий и авторизации.
     */
    public static function cleanHeaders(array $headers): array
    {
        $blocked = [
            'authorization',
            'proxy-authorization',
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ];

        $cleaned = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower((string)$key);
            if (in_array($lowerKey, $blocked, true)) {
                continue;
            }
            // Заголовки в Laravel приходят как массивы строк, берем первое значение
            $cleaned[$lowerKey] = is_array($value) ? ($value[0] ?? '') : (string)$value;
        }

        return $cleaned;
    }
}
