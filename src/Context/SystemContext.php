<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Context;

class SystemContext
{
    public static function capture(): array
    {
        try {
            $durationMs = 0.0;
            if (defined('LARAVEL_START')) {
                $durationMs = round((microtime(true) - LARAVEL_START) * 1000, 2);
            }

            return [
                'php_version'        => PHP_VERSION,
                'laravel_version'    => function_exists('app') ? app()->version() : '',
                'memory_usage_bytes' => memory_get_usage(true),
                'memory_peak_bytes'  => memory_get_peak_usage(true),
                'duration_ms'        => $durationMs,
                'hostname'           => gethostname() ?: 'unknown',
                'os'                 => php_uname('s') . ' ' . php_uname('r'),
                'sapi'               => PHP_SAPI,
                'locale'             => function_exists('app') ? app()->getLocale() : '',
                'config_cached'      => function_exists('app') ? app()->configurationIsCached() : false,
                'routes_cached'      => function_exists('app') ? app()->routesAreCached() : false,
            ];
        } catch (\Throwable) {
            return [
                'php_version'     => PHP_VERSION,
                'laravel_version' => function_exists('app') ? app()->version() : '',
            ];
        }
    }
}
