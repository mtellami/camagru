<?php

declare(strict_types=1);
$root = dirname(__DIR__, 2);
if (!is_file($root . '/.env')) {
    $contents = file_get_contents($root . '/.env.example');
    $contents = preg_replace_callback(
        '/GENERATE_ME/',
        static fn() => bin2hex(random_bytes(32)),
        $contents,
    );
    file_put_contents($root . '/.env', $contents, LOCK_EX);
    chmod($root . '/.env', 0600);
}
