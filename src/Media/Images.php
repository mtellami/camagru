<?php

declare(strict_types=1);

namespace Camagru\Media;

use Camagru\Config;
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

    /** Validate image size/type, decode it, and correct JPEG EXIF orientation. */
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

    /** Validate and deduplicate sticker choices while preserving layer order. */
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

    /** Center-crop the source and apply approved sticker layers to a new canvas. */
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

    /** Validate a PHP upload before decoding its image content. */
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
}
