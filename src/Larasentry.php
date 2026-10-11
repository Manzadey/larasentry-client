<?php

declare(strict_types=1);

namespace Manzadey\Larasentry;

use Illuminate\Support\Facades\Http;
use Manzadey\Larasentry\Breadcrumbs\BreadcrumbRecorder;
use Manzadey\Larasentry\Context\QueueContext;
use Manzadey\Larasentry\Context\RequestContext;
use Manzadey\Larasentry\Context\SystemContext;
use Manzadey\Larasentry\Jobs\SendLarasentryReportJob;
use SplFileObject;
use Throwable;

class Larasentry
{
    /**
     * Capture an exception and send it to the Larasentry server.
     */
    public function captureException(Throwable $exception): void
    {
        try {
            $payload = $this->buildPayload($exception);

            $queue = config('larasentry.queue');
            if (!empty($queue)) {
                $serverUrl = config('larasentry.server_url') ?? config('larasentry.url') ?? 'http://127.0.0.1:8080';
                $url = rtrim((string)$serverUrl, '/') . '/api/events';
                $apiKey = (string)(config('larasentry.api_key') ?? '');

                if (!empty($url) && !empty($apiKey)) {
                    $job = new SendLarasentryReportJob($url, $apiKey, $payload);

                    if (is_string($queue) && $queue !== 'true') {
                        $job->onQueue($queue);
                    }

                    $connection = config('larasentry.queue_connection');
                    if (!empty($connection)) {
                        $job->onConnection($connection);
                    }

                    if (function_exists('dispatch')) {
                        dispatch($job);
                    } else {
                        app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch($job);
                    }

                    return;
                }
            }

            $this->sendPayload($payload);
        } catch (Throwable) {
            // Тихий сбой, никогда не ломаем клиентское приложение
        }
    }

    /**
     * Build the event payload with exception details and runtime context.
     */
    public function buildPayload(Throwable $exception): array
    {
        $isConsole = function_exists('app') ? app()->runningInConsole() : (PHP_SAPI === 'cli');
        $request = (!$isConsole && function_exists('request')) ? request() : null;

        $payload = [
            'exception_class' => get_class($exception),
            'message'         => $exception->getMessage(),
            'file'            => $exception->getFile(),
            'line'            => $exception->getLine(),
            'stack_trace'     => $exception->getTraceAsString(),
            'release'         => config('larasentry.release') ?? config('app.version') ?? '1.0.0',
            'environment'     => config('larasentry.environment') ?? (function_exists('app') ? app()->environment() : 'production'),
            'php_version'     => PHP_VERSION,
            'laravel_version' => function_exists('app') ? app()->version() : '',
            'app_url'         => config('larasentry.app_url') ?? config('app.url') ?? '',
            'request_url'     => $request ? $request->fullUrl() : '',
            'request_method'  => $request ? $request->method() : 'CLI',
        ];

        $tags = config('larasentry.tags');
        if (!empty($tags) && is_array($tags)) {
            $payload['tags'] = $tags;
        }

        // 1. Code snippet (если включен)
        if (config('larasentry.send_code_snippet', true)) {
            $snippet = $this->extractCodeSnippet($exception->getFile(), $exception->getLine());
            if ($snippet !== null) {
                $payload['code_snippet'] = $snippet;
            }
        }

        // 2. Route & HTTP Request Context
        if (config('larasentry.collect_route', true)) {
            $payload['route'] = RequestContext::captureRoute();
        }
        if (config('larasentry.collect_request', true)) {
            $payload['http_request'] = RequestContext::captureRequest();
        }

        // 3. Execution Context (Queue / Console / HTTP)
        if (config('larasentry.collect_queue', true)) {
            $payload['execution'] = QueueContext::captureExecution();
        }

        // 4. Breadcrumbs
        if (config('larasentry.collect_breadcrumbs', true)) {
            $payload['breadcrumbs'] = BreadcrumbRecorder::getInstance()->toArray();
        }

        // 5. System Resources Context
        if (config('larasentry.collect_system', true)) {
            $payload['system'] = SystemContext::capture();
        }

        return $payload;
    }

    /**
     * Send event payload to the Larasentry server.
     */
    public function sendPayload(array|string $payloadOrUrl, ?string $apiKey = null, ?array $payload = null): void
    {
        try {
            if (is_array($payloadOrUrl)) {
                $actualPayload = $payloadOrUrl;
                $serverUrl = config('larasentry.server_url') ?? config('larasentry.url') ?? 'http://127.0.0.1:8080';
                $actualUrl = rtrim((string)$serverUrl, '/') . '/api/events';
                $actualApiKey = (string)(config('larasentry.api_key') ?? '');
            } else {
                $actualUrl = $payloadOrUrl;
                $actualApiKey = $apiKey ?? (string)(config('larasentry.api_key') ?? '');
                $actualPayload = $payload ?? [];
            }

            if (empty($actualUrl) || empty($actualApiKey)) {
                return;
            }

            $timeout = (int)config('larasentry.timeout', 2);

            Http::withHeaders([
                'Authorization' => 'Bearer ' . $actualApiKey,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])
            ->connectTimeout(1)
            ->timeout($timeout)
            ->post($actualUrl, $actualPayload);
        } catch (Throwable) {
            // Тихий сбой, никогда не ломаем клиентское приложение
        }
    }

    /**
     * Extract code snippet surrounding the error line.
     */
    protected function extractCodeSnippet(string $filePath, int $line): ?array
    {
        try {
            if (!is_file($filePath) || !is_readable($filePath)) {
                return null;
            }

            $totalLines = (int)config('larasentry.snippet_lines', 9);
            $half = (int)floor($totalLines / 2);

            $start = max(1, $line - $half);
            $end = $line + $half;

            $file = new SplFileObject($filePath);
            $lines = [];

            $file->seek($start - 1);
            $currentLine = $start;

            while (!$file->eof() && $currentLine <= $end) {
                $lineContent = rtrim((string)$file->fgets(), "\r\n");
                $lines[] = [
                    'number'   => $currentLine,
                    'code'     => $lineContent,
                    'is_error' => $currentLine === $line,
                ];
                $currentLine++;
            }

            return [
                'file'   => $filePath,
                'line'   => $line,
                'lines'  => $lines,
                'source' => 'client',
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
