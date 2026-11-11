<?php
declare(strict_types=1);
namespace Camagru;

final class Http
{
    public static function jsonRequested(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }

    public static function redirect(string $path): never
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\r\n\\\\]/', $path)) {
            $path = '/';
        }
        header('Location: ' . $path, true, 303);
        exit;
    }

    public static function success(string $message, string $path, array $data = [], int $status = 200): never
    {
        if (self::jsonRequested()) {
            self::json(['ok' => true, 'data' => array_merge(['message' => $message, 'redirect' => $path], $data)], $status);
        }
        $_SESSION['flash'] = $message;
        self::redirect($path);
    }

    public static function render(string $template, array $data = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        extract($data, EXTR_SKIP);
        $user = Auth::user();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        ob_start();
        require Config::root() . '/templates/' . $template . '.php';
        $content = ob_get_clean();
        require Config::root() . '/templates/layout.php';
        exit;
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function headers(): void
    {
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' blob: data:; media-src 'self' blob:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: camera=(self), microphone=()');
        header('Cache-Control: no-store');
    }

    public static function input(string $key): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_string($value)) { throw new HttpError(422, 'Invalid form data.'); }
        return $value;
    }
}
