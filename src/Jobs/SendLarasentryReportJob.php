<?php

namespace Manzadey\Larasentry\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendLarasentryReportJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $url,
        public string $apiKey,
        public array $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            app('larasentry')->sendPayload($this->url, $this->apiKey, $this->payload);
        } catch (Throwable $e) {
            // Failsafe: do not crash the queue worker
            if (php_sapi_name() === 'cli') {
                echo "Larasentry Job Failed: " . $e->getMessage() . "\n";
            }
        }
    }
}
