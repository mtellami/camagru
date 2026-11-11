<?php
declare(strict_types=1);
$directory = dirname(__DIR__) . '/public/overlays';
if (!is_dir($directory)) { mkdir($directory, 0755, true); }
$names = ['orbit', 'bloom', 'confetti', 'postcard', 'sunshine', 'midnight'];
foreach ($names as $index => $name) {
    $image = imagecreatetruecolor(1280, 960);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagealphablending($image, true);
    $colors = [[193,108,66], [224,129,153], [124,152,120], [244,232,205], [237,181,64], [82,96,160]];
    [$r,$g,$b] = $colors[$index];
    $color = imagecolorallocatealpha($image, $r, $g, $b, 12);
    if ($name === 'postcard') {
        imagesetthickness($image, 36);
        imagerectangle($image, 25, 25, 1255, 935, $color);
        imagesetthickness($image, 3);
        imagerectangle($image, 55, 55, 1225, 905, $color);
    } elseif ($name === 'confetti') {
        for ($i=0; $i<45; $i++) {
            $x = ($i * 173 + 29) % 1280;
            $y = ($i * 137 + 31) % 960;
            if ($x > 180 && $x < 1100 && $y > 150 && $y < 810) { continue; }
            imagefilledellipse($image, $x, $y, 16 + ($i % 3) * 8, 16 + ($i % 3) * 8, $color);
        }
    } elseif ($name === 'bloom') {
        foreach ([[90,100], [1190,860], [1200,80], [80,880]] as [$x,$y]) {
            for ($i=0; $i<6; $i++) { imagefilledellipse($image, (int) ($x+45*cos($i*M_PI/3)), (int) ($y+45*sin($i*M_PI/3)), 65, 65, $color); }
            imagefilledellipse($image, $x, $y, 42, 42, imagecolorallocate($image, 244, 202, 102));
        }
    } elseif ($name === 'sunshine') {
        imagefilledellipse($image, 1180, 90, 150, 150, $color);
        imagesetthickness($image, 12);
        for ($i=0; $i<12; $i++) { imageline($image, (int) (1180+100*cos($i*M_PI/6)), (int) (90+100*sin($i*M_PI/6)), (int) (1180+140*cos($i*M_PI/6)), (int) (90+140*sin($i*M_PI/6)), $color); }
    } else {
        imagesetthickness($image, 8);
        imagearc($image, 50, 30, 450, 450, 0, 360, $color);
        imagearc($image, 1240, 930, 450, 450, 0, 360, $color);
        for ($i=0; $i<16; $i++) {
            $x = ($i*211+25)%1280;
            $y = $i%2 === 0 ? 55 : 905;
            imageline($image, $x-9, $y, $x+9, $y, $color);
            imageline($image, $x, $y-9, $x, $y+9, $color);
        }
    }
    imagepng($image, $directory . '/' . $name . '.png');
}
