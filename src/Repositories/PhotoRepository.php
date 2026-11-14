<?php

declare(strict_types=1);

namespace Camagru\Repositories;

use Camagru\Database\Database;
use Camagru\Security\Auth;
use Camagru\Http\HttpError;

final class PhotoRepository
{
    /** Read and validate a positive page number from the query string. */
    public function page(string $key): int
    {
        $value = $_GET[$key] ?? '1';
        if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,5}\z/', $value)) {
            throw new HttpError(422, 'Invalid page number.');
        }
        return (int) $value;
    }

    /** Build the shared photo projection with author, counts, and current-user liked state. */
    private function select(): string
    {
        return 'SELECT p.*,u.username,(SELECT COUNT(*) FROM likes l WHERE l.photo_id=p.id) AS likes,(SELECT COUNT(*) FROM comments c WHERE c.photo_id=p.id) AS comments,EXISTS(SELECT 1 FROM likes l WHERE l.photo_id=p.id AND l.user_id=?) AS liked FROM photos p JOIN users u ON u.id=p.user_id ';
    }

    /** Find a ready photo or raise a 404 for unavailable creations. */
    public function find(int $id): array
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

    /** Read a batch of ready photos using a cursor or page, optionally filtered by owner. */
    public function gallery(?int $owner): array
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
}
