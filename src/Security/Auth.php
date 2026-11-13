<?php

declare(strict_types=1);

namespace Camagru\Security;

use Camagru\Config;
use Camagru\Database\Database;
use Camagru\Http\Http;
use Camagru\Http\HttpError;
use Camagru\Mail\Outbox;
use Camagru\Mail\Email;

final class Auth
{
    private static bool $loaded = false;
    private static ?array $current = null;

    public static function start(): void
    {
        session_name('camagru');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Config::get('COOKIE_SECURE') === '1',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $now = time();
        if (
            isset($_SESSION['user_id']) &&
            ($now - ($_SESSION['seen'] ?? 0) > 1800 || $now - ($_SESSION['started'] ?? 0) > 86400)
        ) {
            self::logout();
        }
        $_SESSION['seen'] = $now;
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            if (isset($_SESSION['user_id'])) {
                $user = Database::query(
                    'SELECT id, username, email, notify_comments, auth_version, verified_at FROM users WHERE id=?',
                    [$_SESSION['user_id']],
                )->fetch();
                if (
                    $user &&
                    $user['verified_at'] &&
                    (int) $user['auth_version'] === (int) ($_SESSION['auth_version'] ?? 0)
                ) {
                    self::$current = $user;
                } else {
                    self::logout();
                }
            }
        }
        return self::$current;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            if (Http::jsonRequested()) {
                throw new HttpError(401, 'Please sign in to continue.');
            }
            $_SESSION['flash'] = 'Please sign in to continue.';
            Http::redirect('/login');
        }
        return $user;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION = [
            'user_id' => $user['id'],
            'auth_version' => $user['auth_version'],
            'started' => time(),
            'seen' => time(),
            'csrf' => bin2hex(random_bytes(32)),
        ];
        self::$loaded = true;
        self::$current = null;
    }

    public static function logout(): void
    {
        $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
        session_regenerate_id(true);
        self::$current = null;
        self::$loaded = true;
    }

    public static function csrf(): void
    {
        $received = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? Http::input('csrf');
        if (!is_string($received) || !hash_equals($_SESSION['csrf'] ?? '', $received)) {
            throw new HttpError(403, 'This form has expired. Reload the page and try again.');
        }
        // A no-referrer policy makes browsers send the literal "null" origin on
        // native form POSTs. The synchronizer token remains mandatory in that case.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($origin === null || $origin === 'null') {
            $origin = $_SERVER['HTTP_REFERER'] ?? null;
        }
        if ($origin !== null) {
            $expected = parse_url(Config::url());
            $actual = parse_url($origin);
            if (
                !$actual ||
                ($actual['scheme'] ?? '') !== ($expected['scheme'] ?? '') ||
                ($actual['host'] ?? '') !== ($expected['host'] ?? '') ||
                ($actual['port'] ?? null) !== ($expected['port'] ?? null)
            ) {
                throw new HttpError(403, 'Cross-site requests are not allowed.');
            }
        }
    }

    public static function rate(
        string $action,
        int $limit,
        int $seconds,
        ?string $subject = null,
    ): void {
        $subject ??= $_SERVER['REMOTE_ADDR'] ?? 'local';
        $key = hash_hmac('sha256', $action . ':' . $subject, Config::get('APP_KEY'));
        $count = Database::query(
            "INSERT INTO rate_limits(key, count, expires_at) VALUES (?,1,NOW() + CAST(? AS INTEGER)*INTERVAL '1 second') ON CONFLICT(key) DO UPDATE SET count=CASE WHEN rate_limits.expires_at<NOW() THEN 1 ELSE rate_limits.count+1 END, expires_at=CASE WHEN rate_limits.expires_at<NOW() THEN EXCLUDED.expires_at ELSE rate_limits.expires_at END RETURNING count",
            [$key, $seconds],
        )->fetchColumn();
        if ((int) $count > $limit) {
            if (!headers_sent()) {
                header('Retry-After: ' . $seconds);
            }
            throw new HttpError(429, 'Too many attempts. Please wait and try again.');
        }
    }

    public static function validateUsername(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/\A[A-Za-z0-9_]{3,30}\z/', $value)) {
            throw new HttpError(
                422,
                'Use 3–30 letters, numbers, or underscores for your username.',
                ['username' => 'Invalid username.'],
            );
        }
        return $value;
    }

    public static function validateEmail(string $value): string
    {
        $value = strtolower(trim($value));
        if (strlen($value) > 254 || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new HttpError(422, 'Enter a valid email address.', [
                'email' => 'Invalid email address.',
            ]);
        }
        return $value;
    }

    public static function validatePassword(string $value, string $confirmation): string
    {
        if (
            !mb_check_encoding($value, 'UTF-8') ||
            mb_strlen($value) < 15 ||
            mb_strlen($value) > 128 ||
            !preg_match('/[a-zA-Z]/', $value) ||
            !preg_match('/[0-9]/', $value)
        ) {
            throw new HttpError(422, 'Use 15–128 characters, including a letter and a number.', [
                'password' => 'Password does not meet the requirements.',
            ]);
        }
        if ($value !== $confirmation) {
            throw new HttpError(422, 'Passwords do not match.', [
                'confirmation' => 'Passwords do not match.',
            ]);
        }
        return $value;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public static function currentPassword(array $user, string $password): void
    {
        self::rate('reauth', 10, 900, (string) $user['id']);
        $hash = Database::query('SELECT password_hash FROM users WHERE id=?', [
            $user['id'],
        ])->fetchColumn();
        if (!password_verify($password, $hash)) {
            throw new HttpError(422, 'The current password is incorrect.');
        }
    }

    public static function token(int $userId, string $purpose, ?string $target = null): string
    {
        Database::query(
            'UPDATE account_tokens SET consumed_at=NOW() WHERE user_id=? AND purpose=? AND consumed_at IS NULL',
            [$userId, $purpose],
        );
        $raw = bin2hex(random_bytes(32));
        $seconds = $purpose === 'reset' ? 1800 : 86400;
        Database::query(
            "INSERT INTO account_tokens(user_id,purpose,token_hash,target_email,expires_at) VALUES (?,?,?,?,NOW()+CAST(? AS INTEGER)*INTERVAL '1 second')",
            [$userId, $purpose, hash('sha256', $raw), $target, $seconds],
        );
        $path = $purpose === 'reset' ? '/reset' : '/verify';
        $recipient =
            $target ??
            Database::query('SELECT email FROM users WHERE id=?', [$userId])->fetchColumn();
        $subject = match ($purpose) {
            'reset' => 'Reset your Camagru password',
            'email' => 'Confirm your new email',
            default => 'Welcome to Camagru — confirm your email',
        };
        $message = Email::account($purpose, Config::url($path . '?token=' . $raw));
        Outbox::queue(
            'token:' . hash('sha256', $raw),
            $userId,
            $recipient,
            $subject,
            $message['body'],
            null,
            time() + $seconds,
            $message['html'],
        );
        return $raw;
    }

    public static function consume(string $raw, string $purpose, callable $callback): void
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $raw)) {
            throw new HttpError(422, 'This link is invalid or has expired.');
        }
        Database::transaction(static function () use ($raw, $purpose, $callback): void {
            $token = Database::query(
                'SELECT * FROM account_tokens WHERE token_hash=? AND purpose=? AND consumed_at IS NULL AND expires_at>NOW() FOR UPDATE',
                [hash('sha256', $raw), $purpose],
            )->fetch();
            if (!$token) {
                throw new HttpError(422, 'This link is invalid or has expired.');
            }
            Database::query('UPDATE account_tokens SET consumed_at=NOW() WHERE id=?', [
                $token['id'],
            ]);
            $callback($token);
        });
    }
}
