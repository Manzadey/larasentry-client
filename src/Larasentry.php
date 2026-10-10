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

        $request = request();

        $payload = [
            'exception_class' => get_class($exception),
            'message'         => $exception->getMessage(),
            'file'            => $exception->getFile(),
            'line'            => $exception->getLine(),
            'stack_trace'     => $exception->getTraceAsString(),
            'app_url'         => config('larasentry.app_url'),
            'request_url'     => $request ? $request->fullUrl() : 'CLI',
            'request_method'  => $request ? $request->method() : 'CLI',
        ];

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
}
