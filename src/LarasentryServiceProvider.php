<?php

namespace Manzadey\Larasentry;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

class LarasentryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/larasentry.php', 'larasentry'
        );

        $this->app->singleton('larasentry', function ($app) {
            return new Larasentry();
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/larasentry.php' => config_path('larasentry.php'),
            ], 'larasentry-config');
        }

        // Автоматическая регистрация перехватчика ошибок
        $handler = $this->app->make(ExceptionHandler::class);
        
        if (method_exists($handler, 'reportable')) {
            $handler->reportable(function (Throwable $e) {
                if ($this->shouldReport($e)) {
                    $this->app->make('larasentry')->captureException($e);
                }
            });
        }
    }

    protected function shouldReport(Throwable $e): bool
    {
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
