<?php

declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

use Camagru\Http\App;
use Camagru\Http\Http;
use Camagru\Http\HttpError;
use Camagru\Maintenance;

// Deliver committed email jobs and clean up during ordinary web requests.
// Health probes should only check readiness, without triggering side effects.
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/health') {
    register_shutdown_function(static function (): void {
        if (http_response_code() >= 500) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        Maintenance::run();
    });
}

try {
    new App()->run();
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
    if (Http::jsonRequested()) {
        Http::json(
            [
                'ok' => false,
                'error' => ['code' => $status, 'message' => $message, 'fields' => $fields],
            ],
            $status,
        );
    }
    if (
        $error instanceof HttpError &&
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' &&
        parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/login' &&
        in_array($status, [403, 422, 429], true)
    ) {
        $username = $_POST['username'] ?? '';
        $_SESSION['login_error'] = [
            'message' => $message,
            'username' => is_string($username) ? mb_substr(trim($username), 0, 30) : '',
        ];
        Http::redirect('/login');
    }
    Http::render('error', ['title' => 'A little interruption', 'message' => $message], $status);
}
