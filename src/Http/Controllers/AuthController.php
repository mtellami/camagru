<?php

declare(strict_types=1);

namespace Camagru\Http\Controllers;

use Camagru\Http\Http;
use Camagru\Http\HttpError;
use Camagru\Security\Auth;
use Camagru\Database\Database;

final class AuthController
{
    /** Render the account form and any one-time login error. */
    public function show(string $path): never
    {
        $token = $_GET['token'] ?? '';
        if (!is_string($token)) {
            throw new HttpError(422, 'Invalid link.');
        }
        $purpose = 'verify';
        if ($path === '/verify' && preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            $purpose =
                Database::query('SELECT purpose FROM account_tokens WHERE token_hash=?', [
                    hash('sha256', $token),
                ])->fetchColumn() ?:
                'verify';
        }
        $loginError = $path === '/login' ? $_SESSION['login_error'] ?? null : null;
        if ($path === '/login') {
            unset($_SESSION['login_error']);
        }
        Http::render('auth', [
            'title' => 'Your account',
            'mode' => substr($path, 1),
            'token' => $token,
            'purpose' => $purpose,
            'loginError' => $loginError,
        ]);
    }

    /** Create an unverified account and queue its confirmation email. */
    public function register(): never
    {
        Auth::rate('register', 5, 3600);
        $username = Auth::validateUsername(Http::input('username'));
        $email = Auth::validateEmail(Http::input('email'));
        $password = Auth::validatePassword(
            Http::input('password'),
            Http::input('confirmation'),
        );
        Database::transaction(static function () use ($username, $email, $password): void {
            $id = Database::query(
                'INSERT INTO users(username,email,password_hash) VALUES (?,?,?) RETURNING id',
                [$username, $email, Auth::hash($password)],
            )->fetchColumn();
            Auth::token((int) $id, 'verify');
        });
        Http::success('Check your inbox to confirm your email before signing in.', '/login');
    }

    /** Verify credentials and start a session for a confirmed account. */
    public function login(): never
    {
        Auth::rate('login-ip', 30, 900);
        $username = trim(Http::input('username'));
        Auth::rate('login-user', 10, 900, strtolower($username));
        $user = Database::query('SELECT * FROM users WHERE LOWER(username)=LOWER(?)', [
            $username,
        ])->fetch();
        // Always perform a real password verification to reduce account-timing differences.
        $fallback =
            '$argon2id$v=19$m=65536,t=4,p=1$Y2FtYWdydS10aW1pbmctc2FsdA$KofQSj5ikcND2I2kq5dCjYJyT7t8sVnrUbB53JQmwQU';
        $valid = password_verify(Http::input('password'), $user['password_hash'] ?? $fallback);
        if (!$user || !$valid) {
            throw new HttpError(422, 'The username or password is incorrect.');
        }
        if (!$user['verified_at']) {
            throw new HttpError(
                403,
                'Confirm your email first. You can request a new link from the sign-in page.',
            );
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            Database::query('UPDATE users SET password_hash=? WHERE id=?', [
                Auth::hash(Http::input('password')),
                $user['id'],
            ]);
        }
        Auth::login($user);
        Http::success('Welcome back, ' . $user['username'] . '.', '/editor');
    }

    /** End the current session and return to the home page. */
    public function logout(): never
    {
        Auth::logout();
        Http::success('You have signed out.', '/');
    }

    /** Queue recovery or confirmation mail without disclosing account existence. */
    public function requestEmail(string $path): never
    {
        Auth::rate('email-request', 10, 3600);
        $email = Auth::validateEmail(Http::input('email'));
        Auth::rate('email-recipient', 3, 900, $email);
        $user = Database::query('SELECT * FROM users WHERE LOWER(email)=?', [$email])->fetch();
        if (
            $user &&
            ($path === '/forgot' ? (bool) $user['verified_at'] : !$user['verified_at'])
        ) {
            Database::transaction(static function () use ($user, $path): void {
                Database::query('SELECT id FROM users WHERE id=? FOR UPDATE', [$user['id']]);
                Auth::token((int) $user['id'], $path === '/forgot' ? 'reset' : 'verify');
            });
        }
        Http::success('If an eligible account exists, an email is on its way.', '/login');
    }

    /** Consume a confirmation token and verify an account or new email. */
    public function verify(): never
    {
        $purpose = Http::input('purpose');
        if (!in_array($purpose, ['verify', 'email'], true)) {
            throw new HttpError(422, 'Invalid confirmation link.');
        }
        Auth::consume(Http::input('token'), $purpose, static function (array $token) use (
            $purpose,
        ): void {
            if ($purpose === 'verify') {
                Database::query(
                    'UPDATE users SET verified_at=COALESCE(verified_at,NOW()) WHERE id=?',
                    [$token['user_id']],
                );
            } else {
                Database::query('UPDATE users SET email=? WHERE id=?', [
                    $token['target_email'],
                    $token['user_id'],
                ]);
            }
        });
        Http::success('Your email is confirmed. You can sign in.', '/login');
    }

    /** Consume a reset token, change the password, and invalidate sessions. */
    public function reset(): never
    {
        Auth::rate('reset', 15, 900);
        $password = Auth::validatePassword(
            Http::input('password'),
            Http::input('confirmation'),
        );
        Auth::consume(Http::input('token'), 'reset', static function (array $token) use (
            $password,
        ): void {
            Database::query(
                'UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',
                [Auth::hash($password), $token['user_id']],
            );
            Database::query(
                "UPDATE account_tokens SET consumed_at=NOW() WHERE user_id=? AND purpose IN ('reset','email') AND consumed_at IS NULL",
                [$token['user_id']],
            );
        });
        Auth::logout();
        Http::success('Your password is updated. Please sign in again.', '/login');
    }
}
