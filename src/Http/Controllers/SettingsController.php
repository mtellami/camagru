<?php

declare(strict_types=1);

namespace Camagru\Http\Controllers;

use Camagru\Http\Http;
use Camagru\Http\HttpError;
use Camagru\Security\Auth;
use Camagru\Database\Database;

final class SettingsController
{
    /** Render preferences for the signed-in user. */
    public function show(): never
    {
        Auth::requireUser();
        Http::render('settings', ['title' => 'Your settings']);
    }

    /** Apply the selected preference, requiring reauthentication for sensitive changes. */
    public function update(string $path): never
    {
        $user = Auth::requireUser();
        if ($path === '/settings/username') {
            Database::query('UPDATE users SET username=? WHERE id=?', [
                Auth::validateUsername(Http::input('username')),
                $user['id'],
            ]);
            Http::success('Your username is updated.', '/settings');
        }
        if ($path === '/settings/notifications') {
            Database::query('UPDATE users SET notify_comments=? WHERE id=?', [
                Http::input('notify') === '1' ? 'true' : 'false',
                $user['id'],
            ]);
            Http::success('Your notification preference is saved.', '/settings');
        }
        Auth::currentPassword($user, Http::input('current_password'));
        if ($path === '/settings/email') {
            $email = Auth::validateEmail(Http::input('email'));
            if (
                Database::query('SELECT 1 FROM users WHERE LOWER(email)=?', [$email])->fetchColumn()
            ) {
                throw new HttpError(422, 'That email address is unavailable.');
            }
            Auth::rate('email-change', 3, 900, (string) $user['id']);
            Database::transaction(static function () use ($user, $email): void {
                Database::query('SELECT id FROM users WHERE id=? FOR UPDATE', [$user['id']]);
                Auth::token((int) $user['id'], 'email', $email);
            });
            Http::success(
                'Check your new inbox to confirm the change. Your current email remains active until then.',
                '/settings',
            );
        }
        if ($path === '/settings/password') {
            $password = Auth::validatePassword(
                Http::input('password'),
                Http::input('confirmation'),
            );
            Database::transaction(static function () use ($user, $password): void {
                Database::query(
                    'UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',
                    [Auth::hash($password), $user['id']],
                );
                Database::query(
                    "UPDATE account_tokens SET consumed_at=NOW() WHERE user_id=? AND purpose IN ('reset','email') AND consumed_at IS NULL",
                    [$user['id']],
                );
            });
            $user['auth_version'] = Database::query('SELECT auth_version FROM users WHERE id=?', [
                $user['id'],
            ])->fetchColumn();
            Auth::login($user);
            Http::success(
                'Your password is updated. Other sessions have been signed out.',
                '/settings',
            );
        }
        throw new HttpError(404, 'Unknown preference.');
    }
}
