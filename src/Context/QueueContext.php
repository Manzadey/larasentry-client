<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Context;

use Illuminate\Contracts\Queue\Job;

class QueueContext
{
    protected static ?array $currentJob = null;

    public static function setCurrentJob(Job $job): void
    {
        try {
            self::$currentJob = [
                'job_name'   => method_exists($job, 'resolveName') ? $job->resolveName() : get_class($job),
                'queue'      => $job->getQueue(),
                'connection' => $job->getConnectionName(),
                'attempts'   => $job->attempts(),
                'max_tries'  => method_exists($job, 'maxTries') ? $job->maxTries() : null,
                'timeout'    => method_exists($job, 'timeout') ? $job->timeout() : 0,
            ];
        } catch (\Throwable) {
            self::$currentJob = null;
        }
    }

    public static function clear(): void
    {
        self::$currentJob = null;
    }

    /**
     * Сбор общего режима исполнения (http | queue | console).
     */
    public static function captureExecution(): array
    {
        $isCli = function_exists('app') ? app()->runningInConsole() : (PHP_SAPI === 'cli');

        if (self::$currentJob !== null) {
            return [
                'type'      => 'queue',
                'is_cli'    => $isCli,
                'command'   => null,
                'queue_job' => self::$currentJob,
            ];
        }

        if ($isCli) {
            $command = isset($_SERVER['argv']) ? implode(' ', $_SERVER['argv']) : 'artisan';
            return [
                'type'      => 'console',
                'is_cli'    => true,
                'command'   => $command,
                'queue_job' => null,
            ];
        }

        return [
            'type'      => 'http',
            'is_cli'    => false,
            'command'   => null,
            'queue_job' => null,
        ];
    }
}
