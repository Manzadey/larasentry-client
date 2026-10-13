<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Console;

use Illuminate\Console\Command;
use Manzadey\Larasentry\Larasentry;
use RuntimeException;
use Throwable;

class TestCommand extends Command
{
    protected $signature = 'larasentry:test {--message= : Текст тестового исключения}';

    protected $description = 'Send a test incident to the Larasentry server';

    public function handle(Larasentry $larasentry): int
    {
        $exception = new RuntimeException(
            $this->option('message') ?: 'This is a test exception sent from the Larasentry Laravel client.'
        );

        try {
            // Очередь обходится намеренно: команда должна сразу показать, дошло ли событие
            $response = $larasentry->send($larasentry->buildPayload($exception));
        } catch (Throwable $e) {
            $this->error('[Larasentry] ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error("[Larasentry] Server responded with HTTP {$response->status()}: {$response->body()}");

            return self::FAILURE;
        }

        $this->info('[Larasentry] Test incident sent to ' . $response->effectiveUri());

        return self::SUCCESS;
    }
}
