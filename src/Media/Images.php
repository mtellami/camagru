<?php

declare(strict_types=1);

namespace Camagru\Media;

use Camagru\Config;
use Camagru\Database\Database;
use Camagru\Http\HttpError;

final class Images
{
    public const OVERLAYS = [
        'exploded' => 'Exploded',
        'cat' => 'Cat',
        'wanted' => 'Wanted',
        'breaking-news' => 'Breaking News',
        '1337' => '1337',
        'game-over' => 'Game Over',
    ];

    public static function decode(string $path, int $size): \GdImage
    {
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new HttpError(413, 'Each image must be no larger than 5 MiB.');
        }
        $mime = new \finfo(FILEINFO_MIME_TYPE)->file($path);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new HttpError(422, 'Choose a JPEG or PNG image.');
        }
        try {
            $info = getimagesize($path);
            if (!$info || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 12000000) {
                throw new HttpError(
                    422,
                    'Images must be at most 12 megapixels and 6000 pixels per side.',
                );
            }
            $image =
                $mime === 'image/jpeg' ? imagecreatefromjpeg($path) : imagecreatefrompng($path);
            if (!$image) {
                throw new HttpError(422, 'This image cannot be decoded.');
            }
            if ($mime === 'image/jpeg') {
                // Missing EXIF is normal; malformed metadata must not emit a warning.
                $exif = @exif_read_data($path);
                $orientation = (int) ($exif['Orientation'] ?? 1);
                if (in_array($orientation, [2, 4, 5, 7], true)) {
                    imageflip($image, IMG_FLIP_HORIZONTAL);
                }
                $angle = match ($orientation) {
                    3, 4 => 180,
                    5, 6 => -90,
                    7, 8 => 90,
                    default => 0,
                };
                if ($angle !== 0) {
                    $image = imagerotate($image, $angle, 0);
                }
            }
            return $image;
        } catch (\ErrorException | \ValueError $error) {
            throw new HttpError(422, 'This image is damaged or unsupported.');
        }
    }

    public static function overlays(mixed $value): array
    {
        // Accept the former single-sticker payload from already-open editors.
        if (is_string($value)) {
            $value = $value === 'none' || $value === '' ? [] : [$value];
        }
        if (!is_array($value) || !array_is_list($value) || count($value) > count(self::OVERLAYS)) {
            throw new HttpError(422, 'Choose valid stickers.');
        }
        foreach ($value as $overlay) {
            if (!is_string($overlay) || !isset(self::OVERLAYS[$overlay])) {
                throw new HttpError(422, 'Choose valid stickers.');
            }
        }
        return array_values(array_unique($value));
    }

    public static function composite(
        \GdImage $source,
        array $overlays,
        int $width = 1280,
        int $height = 960,
    ): \GdImage {
        $overlays = self::overlays($overlays);
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 246, 243, 235));
        $sw = imagesx($source);
        $sh = imagesy($source);
        $cropW = min($sw, ($sh * $width) / $height);
        $cropH = min($sh, ($sw * $height) / $width);
        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            (int) (($sw - $cropW) / 2),
            (int) (($sh - $cropH) / 2),
            $width,
            $height,
            (int) $cropW,
            (int) $cropH,
        );
        imagealphablending($canvas, true);
        foreach ($overlays as $overlay) {
            $layer = imagecreatefrompng(
                Config::root() . '/public/assets/images/overlays/' . $overlay . '.png',
            );
            $scale = min($width / imagesx($layer), $height / imagesy($layer));
            $layerWidth = (int) round(imagesx($layer) * $scale);
            $layerHeight = (int) round(imagesy($layer) * $scale);
            imagecopyresampled(
                $canvas,
                $layer,
                intdiv($width - $layerWidth, 2),
                intdiv($height - $layerHeight, 2),
                0,
                0,
                $layerWidth,
                $layerHeight,
                imagesx($layer),
                imagesy($layer),
            );
            unset($layer);
        }
        return $canvas;
    }

    public static function upload(array $file): \GdImage
    {
        if (
            !is_int($file['error'] ?? null) ||
            !is_string($file['tmp_name'] ?? null) ||
            !is_int($file['size'] ?? null)
        ) {
            throw new HttpError(422, 'Invalid image upload.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpError(
                ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 413 : 422,
                'Choose a valid image no larger than 5 MiB.',
            );
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new HttpError(422, 'Invalid image upload.');
        }
        return self::decode($file['tmp_name'], (int) $file['size']);
    }

    public static function publish(
        int $userId,
        array $overlays,
        string $submission,
        bool $gif,
    ): array {
        $overlays = self::overlays($overlays);
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
                $source = self::upload($file);
                $canvas = self::composite($source, $overlays, $width, $height);
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
