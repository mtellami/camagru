<?php

declare(strict_types=1);

namespace Camagru;

final class Config
{
    /** Reject missing or unusable configuration before the web servers start. */
    public static function validateStartup(): void
    {
        foreach (['APP_URL', 'APP_KEY', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
            $value = self::get($key);
            if (trim($value) === '' || $value === 'GENERATE_ME') {
                throw new \RuntimeException('Set a valid ' . $key . ' in .env before starting the app.');
            }
        }
        if (!preg_match('/\A[a-f0-9]{64}\z/', self::get('APP_KEY'))) {
            throw new \RuntimeException('APP_KEY must contain 32 hex-encoded random bytes.');
        }
        $url = self::get('APP_URL');
        $parts = parse_url($url);
        if (
            !filter_var($url, FILTER_VALIDATE_URL) ||
            !in_array($parts['scheme'] ?? '', ['http', 'https'], true) ||
            isset($parts['user']) ||
            isset($parts['pass']) ||
            isset($parts['query']) ||
            isset($parts['fragment'])
        ) {
            throw new \RuntimeException('APP_URL must be an absolute HTTP(S) URL without credentials, query, or fragment.');
        }
        if (filter_var(self::get('DB_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            throw new \RuntimeException('DB_PORT must be a port number between 1 and 65535.');
        }
        if (!in_array(self::get('COOKIE_SECURE', '0'), ['0', '1'], true)) {
            throw new \RuntimeException('COOKIE_SECURE must be 0 or 1.');
        }
    }

    /** Read an environment value, falling back only when it is absent. */
    public static function get(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    /** Return the repository root used to locate application files. */
    public static function root(): string
    {
        return dirname(__DIR__);
    }
    /** Return the private directory containing media, sessions, and logs. */
    public static function storage(): string
    {
        return self::root() . '/storage';
    }
    /** Build an absolute app URL for links such as account confirmation emails. */
    public static function url(string $path = ''): string
    {
        return rtrim(self::get('APP_URL', 'http://localhost:8080'), '/') . $path;
    }
}
