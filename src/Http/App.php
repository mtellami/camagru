<?php

declare(strict_types=1);

namespace Camagru\Http;

use Camagru\Security\Auth;
use Camagru\Config;
use Camagru\Database\Database;
use Camagru\Media\Images;
use Camagru\Mail\Outbox;
use Camagru\Mail\Email;

final class App
{
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
            $owner = $path === '/editor/photos' ? (int) Auth::requireUser()['id'] : null;
            $result = $this->gallery($owner);
            if (Http::jsonRequested()) {
                Http::json(['ok' => true, 'data' => $result]);
            }
            Http::render('gallery', array_merge(['title' => 'The gallery'], $result));
        }
        if (preg_match('~^/photos/([1-9][0-9]*)$~', $path, $match)) {
            $photo = $this->photo((int) $match[1]);
            $page = $this->page('page');
            $comments = Database::query(
                'SELECT c.*,u.username FROM comments c JOIN users u ON u.id=c.user_id WHERE photo_id=? ORDER BY c.created_at,c.id LIMIT 20 OFFSET ?',
                [$photo['id'], ($page - 1) * 20],
            )->fetchAll();
            Http::render('photo', [
                'title' => 'A moment by ' . $photo['username'],
                'photo' => $photo,
                'comments' => $comments,
                'page' => $page,
            ]);
        }
        if (preg_match('~^/media/([1-9][0-9]*)/(image|thumb|poster)$~', $path, $match)) {
            $this->media((int) $match[1], $match[2]);
        }
        if (
            in_array(
                $path,
                ['/login', '/register', '/forgot', '/resend', '/verify', '/reset'],
                true,
            )
        ) {
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
        if ($path === '/settings') {
            Auth::requireUser();
            Http::render('settings', ['title' => 'Your settings']);
        }
        if ($path === '/editor') {
            $user = Auth::requireUser();
            $result = $this->gallery((int) $user['id']);
            Http::render('editor', ['title' => 'Make a moment', 'history' => $result]);
        }
        if ($path === '/overlays') {
            Http::json(['ok' => true, 'data' => Images::OVERLAYS]);
        }
        throw new HttpError(404, 'We could not find that page.');
    }

    private function post(string $path): never
    {
        if ($path === '/register') {
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
        if ($path === '/login') {
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
        if ($path === '/logout') {
            Auth::logout();
            Http::success('You have signed out.', '/');
        }
        if ($path === '/forgot' || $path === '/resend') {
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
        if ($path === '/verify') {
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
        if ($path === '/reset') {
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
        if (str_starts_with($path, '/settings/')) {
            $this->settings($path);
        }
        if ($path === '/photos' || $path === '/photos/gif') {
            $user = Auth::requireUser();
            Auth::rate('capture', 30, 3600, (string) $user['id']);
            $overlay = Http::input('overlay');
            $submission = Http::input('submission');
            session_write_close();
            $photo = Images::publish(
                (int) $user['id'],
                $overlay,
                $submission,
                $path === '/photos/gif',
            );
            if (Http::jsonRequested()) {
                Http::json(
                    [
                        'ok' => true,
                        'data' => [
                            'message' => 'Your moment is in the gallery.',
                            'redirect' => '/photos/' . $photo['id'],
                            'photo' => $photo,
                        ],
                    ],
                    201,
                );
            }
            Http::redirect('/photos/' . $photo['id']);
        }
        if (
            preg_match('~^/photos/([1-9][0-9]*)/comments/([1-9][0-9]*)/delete$~', $path, $matches)
        ) {
            $user = Auth::requireUser();
            $deleted = Database::query(
                'DELETE FROM comments WHERE id=? AND photo_id=? AND user_id=? RETURNING id',
                [(int) $matches[2], (int) $matches[1], $user['id']],
            )->fetchColumn();
            if (!$deleted) {
                throw new HttpError(403, 'You can only delete your own comments.');
            }
            Http::success('Your comment has been deleted.', '/photos/' . $matches[1]);
        }
        if (
            preg_match('~^/photos/([1-9][0-9]*)/(like|unlike|comments|delete)$~', $path, $matches)
        ) {
            $this->interact((int) $matches[1], $matches[2]);
        }
        throw new HttpError(404, 'We could not find that action.');
    }

    private function settings(string $path): never
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

    private function interact(int $id, string $action): never
    {
        $user = Auth::requireUser();
        $result = Database::transaction(function () use ($id, $action, $user): array {
            $photo = Database::query(
                "SELECT p.*,u.email,u.notify_comments FROM photos p JOIN users u ON u.id=p.user_id WHERE p.id=? AND p.state='ready' FOR UPDATE OF p",
                [$id],
            )->fetch();
            if (!$photo) {
                throw new HttpError(404, 'This photo is no longer available.');
            }
            if ($action === 'delete') {
                if ((int) $photo['user_id'] !== (int) $user['id']) {
                    throw new HttpError(403, 'You can only delete your own creations.');
                }
                Database::query("UPDATE photos SET state='deleting' WHERE id=? AND user_id=?", [
                    $id,
                    $user['id'],
                ]);
                return [];
            }
            if ($action === 'like') {
                Database::query(
                    'INSERT INTO likes(photo_id,user_id) VALUES (?,?) ON CONFLICT DO NOTHING',
                    [$id, $user['id']],
                );
            }
            if ($action === 'unlike') {
                Database::query('DELETE FROM likes WHERE photo_id=? AND user_id=?', [
                    $id,
                    $user['id'],
                ]);
            }
            if ($action === 'comments') {
                Auth::rate('comment', 30, 3600, (string) $user['id']);
                $body = trim(Http::input('body'));
                if (
                    !mb_check_encoding($body, 'UTF-8') ||
                    mb_strlen($body) < 1 ||
                    mb_strlen($body) > 1000
                ) {
                    throw new HttpError(422, 'Comments must contain 1–1,000 characters.');
                }
                $submission = Http::input('submission');
                if (!preg_match('/\A[a-f0-9]{32}\z/', $submission)) {
                    throw new HttpError(422, 'Reload this page before commenting.');
                }
                $comment = Database::query(
                    'INSERT INTO comments(photo_id,user_id,body,submission_key) VALUES (?,?,?,?) ON CONFLICT(user_id,submission_key) DO NOTHING RETURNING *',
                    [$id, $user['id'], $body, $submission],
                )->fetch();
                if ($comment && $photo['notify_comments']) {
                    $message = Email::comment(
                        $user['username'],
                        $body,
                        Config::url('/photos/' . $id),
                    );
                    Outbox::queue(
                        'comment:' . $comment['id'],
                        (int) $photo['user_id'],
                        $photo['email'],
                        'A new comment on your Camagru moment',
                        $message['body'],
                        (int) $comment['id'],
                        null,
                        $message['html'],
                    );
                }
                return [
                    'comment' => $comment
                        ? array_merge($comment, ['username' => $user['username']])
                        : null,
                    'submission' => bin2hex(random_bytes(16)),
                ];
            }
            return [
                'liked' => $action === 'like',
                'count' => (int) Database::query('SELECT COUNT(*) FROM likes WHERE photo_id=?', [
                    $id,
                ])->fetchColumn(),
            ];
        });
        Http::success(
            $action === 'delete' ? 'Your creation has been deleted.' : 'Saved.',
            $action === 'delete' ? '/editor' : '/photos/' . $id,
            $result,
        );
    }

    private function page(string $key): int
    {
        $value = $_GET[$key] ?? '1';
        if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,5}\z/', $value)) {
            throw new HttpError(422, 'Invalid page number.');
        }
        return (int) $value;
    }

    private function select(): string
    {
        return 'SELECT p.*,u.username,(SELECT COUNT(*) FROM likes l WHERE l.photo_id=p.id) AS likes,(SELECT COUNT(*) FROM comments c WHERE c.photo_id=p.id) AS comments,EXISTS(SELECT 1 FROM likes l WHERE l.photo_id=p.id AND l.user_id=?) AS liked FROM photos p JOIN users u ON u.id=p.user_id ';
    }

    private function photo(int $id): array
    {
        $photo = Database::query($this->select() . "WHERE p.id=? AND p.state='ready'", [
            Auth::user()['id'] ?? 0,
            $id,
        ])->fetch();
        if (!$photo) {
            throw new HttpError(404, 'This photo is no longer available.');
        }
        return $photo;
    }

    private function gallery(?int $owner): array
    {
        $page = $this->page('page');
        $params = [Auth::user()['id'] ?? 0];
        $where = "WHERE p.state='ready'";
        if ($owner !== null) {
            $where .= ' AND p.user_id=?';
            $params[] = $owner;
        }
        if (isset($_GET['before'])) {
            if (!is_string($_GET['before']) || strlen($_GET['before']) > 256) {
                throw new HttpError(422, 'Invalid gallery cursor.');
            }
            $cursor = json_decode(base64_decode($_GET['before'], true) ?: '', true);
            if (
                !is_array($cursor) ||
                array_keys($cursor) !== [0, 1] ||
                !is_string($cursor[0]) ||
                !is_int($cursor[1]) ||
                $cursor[1] < 1 ||
                !preg_match(
                    '/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?\+00\z/',
                    $cursor[0],
                )
            ) {
                throw new HttpError(422, 'Invalid gallery cursor.');
            }
            try {
                if (substr($cursor[0], 0, 4) === '0000') {
                    throw new \Exception();
                }
                new \DateTimeImmutable($cursor[0]);
                $errors = \DateTimeImmutable::getLastErrors();
                if ($errors && ($errors['warning_count'] || $errors['error_count'])) {
                    throw new \Exception();
                }
            } catch (\Exception) {
                throw new HttpError(422, 'Invalid gallery cursor.');
            }
            $where .= ' AND (p.created_at,p.id)<(CAST(? AS TIMESTAMPTZ),CAST(? AS BIGINT))';
            $params[] = $cursor[0];
            $params[] = $cursor[1];
        }
        $params[] = isset($_GET['before']) ? 0 : ($page - 1) * 10;
        $photos = Database::query(
            $this->select() . $where . ' ORDER BY p.created_at DESC,p.id DESC LIMIT 11 OFFSET ?',
            $params,
        )->fetchAll();
        $more = count($photos) > 10;
        $photos = array_slice($photos, 0, 10);
        $last = $photos ? $photos[count($photos) - 1] : null;
        return [
            'photos' => $photos,
            'page' => $page,
            'more' => $more,
            'cursor' =>
                $more && $last
                    ? base64_encode(json_encode([$last['created_at'], (int) $last['id']]))
                    : null,
        ];
    }

    private function media(int $id, string $variant): never
    {
        $photo = $this->photo($id);
        session_write_close();
        $extension = $variant === 'image' && $photo['kind'] === 'gif' ? '.gif' : '.jpg';
        $path =
            Config::storage() .
            '/media/' .
            $photo['media_key'] .
            ($variant === 'thumb' ? '-thumb' : '') .
            $extension;
        if (!is_file($path)) {
            throw new HttpError(404, 'This image is unavailable.');
        }
        header('Content-Type: ' . ($extension === '.gif' ? 'image/gif' : 'image/jpeg'));
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit();
    }
}
