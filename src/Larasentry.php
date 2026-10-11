<?php

namespace Manzadey\Larasentry;

use Illuminate\Support\Facades\Http;
use Throwable;

class Larasentry
{
    /**
     * Capture an exception and send it to the Larasentry server asynchronously.
     */
    public function captureException(Throwable $exception): void
    {
        $url = config('larasentry.url');
        $apiKey = config('larasentry.api_key');

        if (empty($url) || empty($apiKey)) {
            // Larasentry is not configured, do nothing
            return;
        }
        
        $url = rtrim($url, '/') . '/api/events';

        $payload = $this->buildPayload($exception);

        try {
            // Отправляем асинхронно, чтобы не тормозить отдачу страницы пользователю
            Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->timeout(2)
            ->async()
            ->post($url, $payload);
        } catch (Throwable $e) {
            // Failsafe
            if (php_sapi_name() === 'cli') {
                echo "Larasentry Request Failed: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Build the event payload with exception details and runtime context.
     */
    public function buildPayload(Throwable $exception): array
    {
        $isConsole = app()->runningInConsole();
        $request = !$isConsole ? request() : null;

        return [
            // Данные об ошибке
            'exception_class' => get_class($exception),
            'message'         => $exception->getMessage(),
            'file'            => $exception->getFile(),
            'line'            => $exception->getLine(),
            'stack_trace'     => $exception->getTraceAsString(),

            // Окружение (определяется пакетом автоматически)
            'php_version'     => PHP_VERSION,
            'environment'     => app()->environment(),
            'release'         => config('larasentry.release') ?? config('app.version'),

            // Контекст HTTP-запроса (или CLI)
            'app_url'         => config('larasentry.app_url') ?? config('app.url'),
            'request_url'     => $request ? $request->fullUrl() : 'CLI',
            'request_method'  => $request ? $request->method() : 'CLI',

            // Теги и системный контекст
            'tags'            => array_merge((array) (config('larasentry.tags') ?? []), [
                'laravel_version' => app()->version(),
                'php_os'          => PHP_OS_FAMILY,
            ]),
        ];
    }
}
