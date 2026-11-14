<?php

declare(strict_types=1);

namespace Camagru;

use Camagru\Database\Database;
use Camagru\Security\Auth;
use Camagru\Http\Http;
use Camagru\Http\HttpError;
use Camagru\Http\Controllers\AuthController;
use Camagru\Http\Controllers\EditorController;
use Camagru\Http\Controllers\GalleryController;
use Camagru\Http\Controllers\PhotoController;
use Camagru\Http\Controllers\SettingsController;

final class App
{
    /** Apply request-wide checks and dispatch to the controller for this URL. */
    public function run(): never
    {
        Http::headers();
        Auth::start();
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        if ($method === 'POST') {
            if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 24 * 1024 * 1024) {
                throw new HttpError(413, 'The complete upload must be below 24 MiB.');
            }
            Auth::csrf();
            $this->post($path);
        }
        if ($method !== 'GET') {
            throw new HttpError(405, 'This method is not supported.');
        }
        if ($path === '/health') {
            Database::query('SELECT 1');
            Http::json(['ok' => true]);
        }
        if ($path === '/') {
            Http::render('home', ['title' => 'Home']);
        }
        if ($path === '/gallery' || $path === '/editor/photos') {
            (new GalleryController())->show($path === '/editor/photos');
        }
        if (preg_match('~^/photos/([1-9][0-9]*)$~', $path, $match)) {
            (new PhotoController())->show((int) $match[1]);
        }
        if (preg_match('~^/media/([1-9][0-9]*)/(image|thumb|poster)$~', $path, $match)) {
            (new PhotoController())->media((int) $match[1], $match[2]);
        }
        if (in_array($path, ['/login', '/register', '/forgot', '/resend', '/verify', '/reset'], true)) {
            (new AuthController())->show($path);
        }
        if ($path === '/settings') {
            (new SettingsController())->show();
        }
        if ($path === '/editor') {
            (new EditorController())->show();
        }
        if ($path === '/overlays') {
            (new EditorController())->overlays();
        }
        throw new HttpError(404, 'We could not find that page.');
    }

    /** Dispatch state-changing routes after the shared CSRF check has passed. */
    private function post(string $path): never
    {
        $accountAction = match ($path) {
            '/register' => 'register',
            '/login' => 'login',
            '/logout' => 'logout',
            '/verify' => 'verify',
            '/reset' => 'reset',
            default => null,
        };
        if ($accountAction !== null) {
            (new AuthController())->$accountAction();
        }
        if ($path === '/forgot' || $path === '/resend') {
            (new AuthController())->requestEmail($path);
        }
        if (str_starts_with($path, '/settings/')) {
            (new SettingsController())->update($path);
        }
        if ($path === '/photos' || $path === '/photos/gif') {
            (new EditorController())->publish($path === '/photos/gif');
        }
        if (preg_match('~^/photos/([1-9][0-9]*)/comments/([1-9][0-9]*)/delete$~', $path, $match)) {
            (new PhotoController())->deleteComment((int) $match[1], (int) $match[2]);
        }
        if (preg_match('~^/photos/([1-9][0-9]*)/(like|unlike|comments|delete)$~', $path, $match)) {
            (new PhotoController())->interact((int) $match[1], $match[2]);
        }
        throw new HttpError(404, 'We could not find that action.');
    }
}
