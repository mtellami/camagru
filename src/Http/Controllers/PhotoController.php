<?php

declare(strict_types=1);

namespace Camagru\Http\Controllers;

use Camagru\Http\Http;
use Camagru\Http\HttpError;
use Camagru\Security\Auth;
use Camagru\Config;
use Camagru\Database\Database;
use Camagru\Mail\Outbox;
use Camagru\Mail\Email;
use Camagru\Repositories\PhotoRepository;

final class PhotoController
{
    /** Render a ready photo and a page of its comments. */
    public function show(int $id): never
    {
        $photo = (new PhotoRepository())->find($id);
        $page = (new PhotoRepository())->page('page');
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

    /** Delete a comment only when it belongs to the signed-in user. */
    public function deleteComment(int $id, int $commentId): never
    {
        $user = Auth::requireUser();
        $deleted = Database::query(
            'DELETE FROM comments WHERE id=? AND photo_id=? AND user_id=? RETURNING id',
            [$commentId, $id, $user['id']],
        )->fetchColumn();
        if (!$deleted) {
            throw new HttpError(403, 'You can only delete your own comments.');
        }
        Http::success('Your comment has been deleted.', '/photos/' . $id);
    }

    /** Lock a ready photo and apply a like, comment, or owner deletion atomically. */
    public function interact(int $id, string $action): never
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

    /** Stream the requested variant only while its photo is ready. */
    public function media(int $id, string $variant): never
    {
        $photo = (new PhotoRepository())->find($id);
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
