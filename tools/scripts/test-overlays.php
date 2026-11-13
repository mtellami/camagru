<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Camagru\Http\HttpError;
use Camagru\Media\Images;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

check(Images::overlays([]) === [], 'No stickers must be allowed.');
check(Images::overlays('none') === [], 'Existing no-sticker requests must work.');
check(Images::overlays('wanted') === ['wanted'], 'Existing single-sticker requests must work.');
check(Images::overlays(['cat', 'exploded', 'cat']) === ['cat', 'exploded'], 'Duplicate stickers must be deduplicated without changing layer order.');

foreach ([null, false, 1, '../cat', ['../cat'], [['cat']], [1], ['key' => 'cat'], array_fill(0, 7, 'cat')] as $invalid) {
    try {
        Images::overlays($invalid);
        throw new RuntimeException('Malformed stickers must be rejected.');
    } catch (HttpError $error) {
        check($error->status === 422, 'Invalid selections must return 422.');
    }
}

foreach ([['cat', 'exploded'], ['exploded', 'cat']] as $selection) {
    foreach ([[1280, 960], [480, 360]] as [$width, $height]) {
        $source = imagecreatetruecolor($width, $height);
        imagefill($source, 0, 0, imagecolorallocate($source, 50, 150, 210));
        $plain = Images::composite($source, [], $width, $height);
        $cat = Images::composite($source, ['cat'], $width, $height);
        $exploded = Images::composite($source, ['exploded'], $width, $height);
        $combined = Images::composite($source, $selection, $width, $height);
        check(imagesx($combined) === $width && imagesy($combined) === $height, 'Photo and GIF dimensions must be preserved.');
        check(imagecolorat($plain, 0, 0) === imagecolorat($source, 0, 0), 'No-sticker output must preserve the source.');
        check(imagecolorat($combined, 0, 0) === imagecolorat($plain, 0, 0), 'Transparent sticker backgrounds must preserve the source.');
        $catVisible = false;
        $explodedVisible = false;
        // Both supplied stickers occupy different parts of the canvas. Confirm
        // each survives composition, at photo and GIF resolutions, in either order.
        for ($y = 0; $y < $height; $y += 5) {
            for ($x = 0; $x < $width; $x += 5) {
                $basePixel = imagecolorat($plain, $x, $y);
                $catPixel = imagecolorat($cat, $x, $y);
                $explodedPixel = imagecolorat($exploded, $x, $y);
                $combinedPixel = imagecolorat($combined, $x, $y);
                $catVisible = $catVisible || ($catPixel !== $basePixel && $explodedPixel === $basePixel && $combinedPixel === $catPixel);
                $explodedVisible = $explodedVisible || ($explodedPixel !== $basePixel && $catPixel === $basePixel && $combinedPixel === $explodedPixel);
            }
        }
        check($catVisible, 'The cat must remain visible alongside another sticker.');
        check($explodedVisible, 'Exploded must remain visible alongside another sticker.');
    }
}

echo "Passed {$checks} sticker validation and composition checks.\n";
