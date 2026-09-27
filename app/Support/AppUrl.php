<?php

namespace App\Support;

/**
 * The public URL of the app, used for every link built outside a web request
 * (mails rendered by the queue worker, Artisan commands).
 *
 * APP_URL wins, unless it is empty or points at this machine. Then the domain
 * Coolify routes to the app (SERVICE_URL_APP) is used, so a forgotten or
 * default APP_URL doesn't send customers to localhost.
 */
class AppUrl
{
    public static function resolve(): string
    {
        $configured = trim((string) env('APP_URL'));

        if ($configured !== '' && ! self::isLocal($configured)) {
            return rtrim($configured, '/');
        }

        // Coolify lists every domain of the service, comma-separated.
        $coolify = trim(explode(',', (string) env('SERVICE_URL_APP'))[0]);

        if ($coolify !== '') {
            return rtrim($coolify, '/');
        }

        return rtrim($configured !== '' ? $configured : 'http://localhost', '/');
    }

    public static function isLocal(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['', 'localhost', '127.0.0.1', '0.0.0.0', '[::1]', '::1'], true)
            || str_ends_with($host, '.localhost');
    }
}
