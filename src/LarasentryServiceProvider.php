<?php

declare(strict_types=1);

namespace Manzadey\Larasentry;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Manzadey\Larasentry\Breadcrumbs\Breadcrumb;
use Manzadey\Larasentry\Breadcrumbs\BreadcrumbRecorder;
use Manzadey\Larasentry\Context\QueueContext;
use Manzadey\Larasentry\Jobs\SendLarasentryReportJob;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Throwable;

class LarasentryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/larasentry.php', 'larasentry');

        $this->app->singleton(Larasentry::class, function () {
            return new Larasentry();
        });

        $this->app->alias(Larasentry::class, 'larasentry');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/larasentry.php' => config_path('larasentry.php'),
            ], 'larasentry-config');
        }

        // Автоматическая регистрация перехватчика ошибок
        $this->registerExceptionHandler();

        // 1. Слушатель очередей для отслеживания текущей джобы
        $this->registerQueueListeners();

        // 2. Сбор хлебных крошек (SQL, логи)
        if (config('larasentry.collect_breadcrumbs', true)) {
            $this->registerDatabaseBreadcrumbs();
            $this->registerLogBreadcrumbs();
        }
    }

    protected function registerExceptionHandler(): void
    {
        try {
            $handler = $this->app->make(ExceptionHandler::class);

            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (Throwable $e) {
                    if ($this->shouldReport($e)) {
                        $this->app->make(Larasentry::class)->captureException($e);
                    }
                });
            }
        } catch (Throwable) {
            // Игнорируем ошибки регистрации обработчика
        }
    }

    protected function registerQueueListeners(): void
    {
        try {
            Event::listen(JobProcessing::class, function (JobProcessing $event) {
                QueueContext::setCurrentJob($event->job);
            });

            Event::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event) {
                // Предотвращаем зацикливание при сбое собственного репортинг джоба
                if (method_exists($event->job, 'resolveName') && $event->job->resolveName() === SendLarasentryReportJob::class) {
                    return;
                }

                QueueContext::setCurrentJob($event->job);
                // Автоматически отправляем ошибку упавшей джобы
                $this->app->make(Larasentry::class)->captureException($event->exception);
            });

            Event::listen(JobFailed::class, function (JobFailed $event) {
                QueueContext::clear();
            });
        } catch (Throwable) {
            // Игнорируем ошибки регистрации слушателя очередей
        }
    }

    protected function registerDatabaseBreadcrumbs(): void
    {
        try {
            DB::listen(function ($query) {
                $sql = (string)$query->sql;
                // Лимит длины тела запроса для экономии памяти
                if (strlen($sql) > 2048) {
                    $sql = substr($sql, 0, 2048) . '... [TRUNCATED]';
                }

                BreadcrumbRecorder::getInstance()->record(new Breadcrumb(
                    category: 'sql',
                    message: $sql,
                    level: 'info',
                    data: [
                        'connection'  => $query->connectionName,
                        'duration_ms' => $query->time,
                        'bindings'    => $query->bindings ?? [],
                    ],
                    timestamp: microtime(true)
                ));
            });
        } catch (Throwable) {
            // Игнорируем ошибки регистрации слушателя
        }
    }

    protected function registerLogBreadcrumbs(): void
    {
        try {
            // Добавляем легковесный обработчик в Monolog
            $logger = Log::getLogger();
            if (method_exists($logger, 'pushHandler')) {
                $logger->pushHandler(new class extends AbstractProcessingHandler {
                    protected function write(LogRecord|array $record): void
                    {
                        // Избегаем зацикливания и слишком частых записей debug-логов
                        $levelName = is_array($record) ? ($record['level_name'] ?? 'info') : $record->level->getName();
                        $message   = is_array($record) ? ($record['message'] ?? '') : $record->message;
                        $channel   = is_array($record) ? ($record['channel'] ?? 'app') : $record->channel;

                        BreadcrumbRecorder::getInstance()->record(new Breadcrumb(
                            category: 'log',
                            message: (string)$message,
                            level: strtolower((string)$levelName),
                            data: ['channel' => (string)$channel],
                            timestamp: microtime(true)
                        ));
                    }
                });
            }
        } catch (Throwable) {
            // Игнорируем ошибки интеграции с логгером
        }
    }

    protected function shouldReport(Throwable $e): bool
    {
        // Защита от зацикливания: не перехватываем ошибки внутри самого Larasentry
        if (str_starts_with($e->getFile(), dirname(__DIR__))) {
            return false;
        }

        // Игнорируем HTTP-ошибки с кодом меньше 500 (например 404 Not Found, 403 Forbidden)
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $e->getStatusCode() >= 500;
        }

        // Игнорируем ошибки валидации (422)
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return false;
        }

        // Игнорируем ошибки авторизации (401)
        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return false;
        }

        // Все остальные (DB ошибки, TypeError и т.д.) - это 500-е, их отправляем
        return true;
    }
}
