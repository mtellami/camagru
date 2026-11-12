<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

use Camagru\Http\App;
use Camagru\Http\Http;
use Camagru\Http\HttpError;

try {
    (new App())->run();
} catch (Throwable $error) {
    $status = 500;
    $message = 'Something went wrong. Please try again shortly.';
    $fields = [];
    if ($error instanceof HttpError) {
        $status = $error->status;
        $message = $error->getMessage();
        $fields = $error->fields;
    } elseif ($error instanceof PDOException && $error->getCode() === '23505') {
        $status = 422;
        $message = 'That username, email address, or submission is already in use.';
    } elseif ($error instanceof PDOException && $error->getCode() === '22007') {
        $status = 422;
        $message = 'Invalid gallery cursor.';
    } else {
        error_log((string) $error);
    }
    if (Http::jsonRequested()) { Http::json(['ok' => false, 'error' => ['code' => $status, 'message' => $message, 'fields' => $fields]], $status); }
    Http::render('error', ['title' => 'A little interruption', 'message' => $message], $status);
}
