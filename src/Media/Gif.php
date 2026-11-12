<?php
declare(strict_types=1);
namespace Camagru\Media;

use Camagru\Http\HttpError;


/** Assembles only single-frame GIFs encoded by our own GD pipeline. */
final class Gif
{
    public static function assemble(array $frames, int $delay = 25): string
    {
        if (count($frames) < 2 || count($frames) > 8) { throw new HttpError(422, 'Select 2–8 frames for an animation.'); }
        $output = '';
        $dimensions = null;
        foreach ($frames as $frame) {
            if (substr($frame, 0, 3) !== 'GIF' || strlen($frame) < 14) { throw new \RuntimeException('Invalid generated GIF frame.'); }
            $size = substr($frame, 6, 4);
            if ($dimensions !== null && $dimensions !== $size) { throw new \RuntimeException('GIF frame dimensions differ.'); }
            $dimensions = $size;
            $packed = ord($frame[10]);
            $paletteSize = ($packed & 0x80) ? 3 * (2 << ($packed & 7)) : 0;
            $palette = substr($frame, 13, $paletteSize);
            $offset = 13 + $paletteSize;
            if ($output === '') {
                $output = 'GIF89a' . $size . "\x70\x00\x00" . "\x21\xff\x0bNETSCAPE2.0\x03\x01\x00\x00\x00";
            }
            while (isset($frame[$offset]) && ord($frame[$offset]) === 0x21) {
                $offset += 2;
                do {
                    $length = ord($frame[$offset] ?? "\0");
                    $offset += 1 + $length;
                } while ($length > 0 && $offset < strlen($frame));
            }
            if (($frame[$offset] ?? '') !== ',') { throw new \RuntimeException('Missing generated GIF image.'); }
            $descriptor = substr($frame, $offset, 10);
            $imagePacked = ord($descriptor[9]);
            $offset += 10;
            if ($imagePacked & 0x80) {
                $paletteSize = 3 * (2 << ($imagePacked & 7));
                $palette = substr($frame, $offset, $paletteSize);
                $offset += $paletteSize;
            } else {
                $descriptor[9] = chr(($imagePacked & 0x40) | 0x80 | ($packed & 7));
            }
            $start = $offset++;
            do {
                $length = ord($frame[$offset] ?? "\0");
                $offset += 1 + $length;
            } while ($length > 0 && $offset < strlen($frame));
            $output .= "\x21\xf9\x04\x04" . pack('v', $delay) . "\x00\x00" . $descriptor . $palette . substr($frame, $start, $offset - $start);
        }
        return $output . ';';
    }
}
