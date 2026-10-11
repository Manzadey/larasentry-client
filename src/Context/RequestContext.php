<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Context;

use Illuminate\Http\Request;

class RequestContext
{
    /**
     * Сбор данных маршрута Laravel.
     */
    public static function captureRoute(?Request $request = null): ?array
    {
        try {
            $request = $request ?? (function_exists('request') ? request() : null);
            if (!$request) {
                return null;
            }

            $route = $request->route();
            if (!$route) {
                return null;
            }

            $customKeys = function_exists('config') ? (array)config('larasentry.scrub_fields', []) : [];

            return [
                'name'       => method_exists($route, 'getName') ? $route->getName() : null,
                'action'     => method_exists($route, 'getActionName') ? $route->getActionName() : null,
                'middleware' => method_exists($route, 'gatherMiddleware') ? $route->gatherMiddleware() : [],
                'parameters' => method_exists($route, 'parameters') ? Sanitizer::clean($route->parameters(), $customKeys) : [],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Сбор входящего HTTP-запроса.
     */
    public static function captureRequest(?Request $request = null): ?array
    {
        try {
            $request = $request ?? (function_exists('request') ? request() : null);
            if (!$request || (function_exists('app') && app()->runningInConsole())) {
                return null;
            }

            $customKeys = function_exists('config') ? (array)config('larasentry.scrub_fields', []) : [];
            $maxBytes   = function_exists('config') ? (int)config('larasentry.max_body_bytes', 32768) : 32768;

            // Обработка тела запроса
            $rawBody = $request->except($customKeys);
            $body = Sanitizer::clean($rawBody, $customKeys);

            // Ограничение объема body
            $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);
            if ($jsonBody !== false && strlen($jsonBody) > $maxBytes) {
                $body = ['_truncated' => true, '_size_bytes' => strlen($jsonBody)];
            }

            return [
                'url'          => $request->fullUrl(),
                'method'       => $request->method(),
                'query_string' => $request->getQueryString() ?? '',
                'query_params' => Sanitizer::clean($request->query(), $customKeys),
                'headers'      => Sanitizer::cleanHeaders($request->headers->all()),
                'client_ip'    => $request->ip() ?? '',
                'body'         => $body,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
