<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Manzadey\Larasentry\LarasentryServiceProvider;
use Orchestra\Testbench\TestCase;

class TestCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LarasentryServiceProvider::class];
    }

    public function test_sends_incident_synchronously_even_with_queue(): void
    {
        Http::fake();
        config(['larasentry.url' => 'http://sentry.test', 'larasentry.queue' => 'default']);

        $this->artisan('larasentry:test', ['--message' => 'ping'])->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->url() === 'http://sentry.test/api/events'
            && $r['message'] === 'ping'
            && $r['exception_class'] === \RuntimeException::class);
    }

    public function test_fails_when_server_rejects(): void
    {
        Http::fake(['*' => Http::response('forbidden', 403)]);
        config(['larasentry.url' => 'http://sentry.test']);

        $this->artisan('larasentry:test')
            ->expectsOutputToContain('HTTP 403')
            ->assertFailed();
    }

    public function test_fails_when_url_missing(): void
    {
        Http::fake();
        config(['larasentry.url' => '', 'larasentry.server_url' => '']);

        $this->artisan('larasentry:test')
            ->expectsOutputToContain('not configured')
            ->assertFailed();

        Http::assertNothingSent();
    }
}
