<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Tests;

use Exception;
use Illuminate\Support\Facades\Context;
use Manzadey\Larasentry\LarasentryServiceProvider;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Throwable;

class AppContextTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LarasentryServiceProvider::class];
    }

    private function payload(Throwable $exception): array
    {
        return app('larasentry')->buildPayload($exception);
    }

    public function test_shared_and_exception_context_are_sent(): void
    {
        Context::add('trace_id', 't-1');

        $payload = $this->payload(new ContextAwareException(['order_id' => 42]));

        $this->assertSame(['trace_id' => 't-1'], $payload['context']['shared']);
        $this->assertSame(['order_id' => 42], $payload['context']['exception']);
    }

    public function test_payload_has_no_context_key_when_nothing_to_send(): void
    {
        $this->assertArrayNotHasKey('context', $this->payload(new RuntimeException('boom')));
    }

    public function test_hidden_context_is_not_sent(): void
    {
        Context::addHidden('internal', 'x');

        $this->assertArrayNotHasKey('context', $this->payload(new RuntimeException('boom')));
    }

    public function test_throwing_context_method_keeps_shared_block(): void
    {
        Context::add('trace_id', 't-1');

        $payload = $this->payload(new BrokenContextException());

        $this->assertSame(['trace_id' => 't-1'], $payload['context']['shared']);
        $this->assertArrayNotHasKey('exception', $payload['context']);
    }

    public function test_non_array_exception_context_is_ignored(): void
    {
        $this->assertArrayNotHasKey('context', $this->payload(new StringContextException()));
    }

    public function test_secret_keys_are_filtered(): void
    {
        Context::add('api_token', 'abc');

        $this->assertSame('[FILTERED]', $this->payload(new RuntimeException('boom'))['context']['shared']['api_token']);
    }

    public function test_oversized_block_is_replaced_with_marker(): void
    {
        config(['larasentry.max_context_bytes' => 20]);
        Context::add('blob', str_repeat('a', 100));

        $shared = $this->payload(new RuntimeException('boom'))['context']['shared'];

        $this->assertTrue($shared['_truncated']);
        $this->assertGreaterThan(20, $shared['_size']);
    }

    public function test_list_context_is_wrapped_into_object(): void
    {
        $payload = $this->payload(new ContextAwareException([1, 2]));

        $this->assertStringContainsString('"exception":{"value":[1,2]}', json_encode($payload['context']));
    }

    public function test_invalid_utf8_block_is_replaced_and_payload_stays_encodable(): void
    {
        Context::add('raw', "\xB1\x31");

        $payload = $this->payload(new RuntimeException('boom'));

        $this->assertArrayHasKey('_invalid', $payload['context']['shared']);
        $this->assertNotFalse(json_encode($payload));
    }

    public function test_failing_sanitizer_drops_block_but_keeps_event(): void
    {
        Context::add('trace_id', 't-1');

        $payload = $this->payload(new ContextAwareException(['o' => new ThrowingStringable()]));

        $this->assertSame(['trace_id' => 't-1'], $payload['context']['shared']);
        $this->assertArrayNotHasKey('exception', $payload['context']);
    }

    public function test_empty_max_bytes_falls_back_to_default(): void
    {
        config(['larasentry.max_context_bytes' => '']);
        Context::add('trace_id', 't-1');

        $this->assertSame(['trace_id' => 't-1'], $this->payload(new RuntimeException('boom'))['context']['shared']);
    }

    public function test_collect_context_flag_disables_block(): void
    {
        config(['larasentry.collect_context' => false]);
        Context::add('trace_id', 't-1');

        $this->assertArrayNotHasKey('context', $this->payload(new RuntimeException('boom')));
    }
}

class ContextAwareException extends Exception
{
    public function __construct(private readonly array $data)
    {
        parent::__construct('context aware');
    }

    public function context(): array
    {
        return $this->data;
    }
}

class BrokenContextException extends Exception
{
    public function context(): array
    {
        throw new RuntimeException('context() is broken');
    }
}

class StringContextException extends Exception
{
    public function context(): string
    {
        return 'not an array';
    }
}

class ThrowingStringable
{
    public function __toString(): string
    {
        throw new RuntimeException('__toString() is broken');
    }
}
