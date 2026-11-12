<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Camagru\\')) {
        $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});
date_default_timezone_set('UTC');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
