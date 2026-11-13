<?php

declare(strict_types=1);

namespace Camagru;

final class Config
{
    public static function get(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    public static function root(): string
    {
        return dirname(__DIR__);
    }
    public static function storage(): string
    {
        return self::root() . '/storage';
    }
    public static function url(string $path = ''): string
    {
        return rtrim(self::get('APP_URL', 'http://localhost:8080'), '/') . $path;
    }
}
