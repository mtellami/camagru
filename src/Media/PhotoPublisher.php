<?php

declare(strict_types=1);

namespace Camagru\Media;

use Camagru\Config;
use Camagru\Database\Database;
use Camagru\Http\HttpError;

final class PhotoPublisher
{
    /** Deduplicate a submission, save composed media, and expose it only when ready. */
    public static function publish(
        int $userId,
        array $overlays,
        string $submission,
        bool $gif,
    ): array {
        $overlays = Images::overlays($overlays);
        if (!preg_match('/\A[a-f0-9]{32}\z/', $submission)) {
            throw new HttpError(422, 'Invalid capture request. Reload the editor.');
        }
        $previous = Database::query('SELECT * FROM photos WHERE user_id=? AND submission_key=?', [
            $userId,
            $submission,
        ])->fetch();
        if ($previous) {
            if ($previous['state'] !== 'ready') {
                throw new HttpError(409, 'This capture is still being processed.');
            }
            return $previous;
        }
        if ($gif) {
            $upload = $_FILES['frames'] ?? [];
            $names = $upload['name'] ?? [];
            if (
                !is_array($names) ||
                count($names) < 2 ||
                count($names) > 8 ||
                !is_array($upload['size'] ?? null)
            ) {
                throw new HttpError(422, 'Choose 2–8 image frames in playback order.');
            }
            foreach ($upload['size'] as $size) {
                if (!is_int($size)) {
                    throw new HttpError(422, 'Invalid frame upload.');
                }
            }
            if (array_sum($upload['size']) > 20 * 1024 * 1024) {
                throw new HttpError(413, 'An animation can contain at most 20 MiB of input.');
            }
            $files = [];
            foreach (array_keys($names) as $i) {
                $files[] = [
                    'tmp_name' => $upload['tmp_name'][$i],
                    'error' => $upload['error'][$i],
                    'size' => $upload['size'][$i],
                ];
            }
        } else {
            $files = [$_FILES['image'] ?? []];
        }
        $width = $gif ? 480 : 1280;
        $height = $gif ? 360 : 960;
        $key = bin2hex(random_bytes(16));
        $directory = Config::storage() . '/media';
        $paths = [];
        $row = Database::query(
            "INSERT INTO photos(user_id,kind,state,media_key,width,height,frame_count,submission_key) VALUES (?,?,'staging',?,?,?,?,?) RETURNING *",
            [$userId, $gif ? 'gif' : 'photo', $key, $width, $height, count($files), $submission],
        )->fetch();
        try {
            $frames = [];
            foreach ($files as $index => $file) {
                $source = Images::upload($file);
                $canvas = Images::composite($source, $overlays, $width, $height);
                unset($source);
                if ($index === 0) {
                    $poster = $directory . '/' . $key . '.jpg';
                    $paths[] = $poster;
                    imagejpeg($canvas, $poster . '.tmp', 90);
                    rename($poster . '.tmp', $poster);
                    $thumb = imagescale($canvas, 320, 240);
                    $path = $directory . '/' . $key . '-thumb.jpg';
                    $paths[] = $path;
                    imagejpeg($thumb, $path . '.tmp', 85);
                    rename($path . '.tmp', $path);
                    unset($thumb);
                }
                if ($gif) {
                    imagetruecolortopalette($canvas, true, 256);
                    ob_start();
                    imagegif($canvas);
                    $frames[] = ob_get_clean();
                }
                unset($canvas);
            }
            if ($gif) {
                $path = $directory . '/' . $key . '.gif';
                $paths[] = $path;
                file_put_contents($path . '.tmp', Gif::assemble($frames), LOCK_EX);
                rename($path . '.tmp', $path);
            }
            Database::query("UPDATE photos SET state='ready' WHERE id=?", [$row['id']]);
            $row['state'] = 'ready';
            return $row;
        } catch (\Throwable $error) {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
                if (is_file($path . '.tmp')) {
                    unlink($path . '.tmp');
                }
            }
            Database::query('DELETE FROM photos WHERE id=?', [$row['id']]);
            throw $error;
        }
    }

    /** Remove deleted creations and abandoned staging files with their metadata. */
    public static function cleanup(): void
    {
        $rows = Database::query(
            "SELECT id,media_key FROM photos WHERE state='deleting' OR (state='staging' AND created_at<NOW()-INTERVAL '10 minutes')",
        )->fetchAll();
        foreach ($rows as $row) {
            foreach (glob(Config::storage() . '/media/' . $row['media_key'] . '*') as $path) {
                unlink($path);
            }
            Database::query("DELETE FROM photos WHERE id=? AND state<>'ready'", [$row['id']]);
        }
    }
}
