<?php
/**
 * backend/config/env.php
 *
 * Shared .env loader. Include this from any file that needs access to
 * environment-based config (DB credentials, R2 credentials, etc.) instead
 * of duplicating the Dotenv bootstrap logic in every file.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$envPath = __DIR__ . '/../'; // backend/.env
if (file_exists($envPath . '.env')) {
    Dotenv\Dotenv::createImmutable($envPath)->safeLoad();
}

if (!function_exists('app_env')) {
    /**
     * Reads an environment variable, working around PHP setups where
     * putenv() is disabled and getenv() can't see what Dotenv loaded.
     */
    function app_env(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
}