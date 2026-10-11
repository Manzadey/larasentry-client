<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Manzadey\Larasentry\LarasentryServiceProvider;
use Orchestra\Testbench\TestCase;
use RuntimeException;

class ServerUrlTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LarasentryServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        config(['larasentry.api_key' => 'secret', 'larasentry.queue' => null]);
    }

    private function capture(): void
    {
        app('larasentry')->captureException(new RuntimeException('boom'));
    }

    public function test_empty_url_sends_nothing(): void
    {
        config(['larasentry.url' => '  ', 'larasentry.server_url' => '']);

        $this->capture();

        Http::assertNothingSent();
    }

    public function test_unset_url_keys_send_nothing(): void
    {
        config(['larasentry.url' => null, 'larasentry.server_url' => null]);

        $this->capture();

        Http::assertNothingSent();
    }

    public function test_only_url_is_used_with_bearer_header(): void
    {
        config(['larasentry.url' => 'http://sentry.test/', 'larasentry.server_url' => 'http://127.0.0.1:8080']);

        $this->capture();

        Http::assertSent(fn (Request $r) => $r->url() === 'http://sentry.test/api/events'
            && $r->header('Authorization') === ['Bearer secret']);
    }

    public function test_only_server_url_is_used(): void
    {
        config(['larasentry.url' => 'http://127.0.0.1:8080', 'larasentry.server_url' => 'http://alias.test']);

        $this->capture();

        Http::assertSent(fn (Request $r) => $r->url() === 'http://alias.test/api/events');
    }

    public function test_url_wins_over_server_url(): void
    {
        config(['larasentry.url' => 'http://a.test', 'larasentry.server_url' => 'http://b.test']);

        $this->capture();

        Http::assertSent(fn (Request $r) => $r->url() === 'http://a.test/api/events');
    }
}
