<?php
declare(strict_types=1);

/**
 * Maak webvriendelijke JPG-varianten (liggend / staand / vierkant) uit bronfoto's.
 */
$root = dirname(__DIR__) . '/assets/media/press/author';

function load_image(string $path)
{
    $info = getimagesize($path);
    if (!$info) {
        throw new RuntimeException("Kan niet lezen: $path");
    }
    return match ($info[2]) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($path),
        IMAGETYPE_PNG => imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : throw new RuntimeException('webp'),
        default => throw new RuntimeException('type'),
    };
}

function save_jpeg($im, string $path, int $quality = 88): void
{
    imagejpeg($im, $path, $quality);
}

/** Center-crop naar aspect ratio w:h en schaal naar maxW. */
function crop_ratio($src, float $ratioW, float $ratioH, int $maxW): GdImage
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    $targetRatio = $ratioW / $ratioH;
    $srcRatio = $sw / $sh;
    if ($srcRatio > $targetRatio) {
        $cropH = $sh;
        $cropW = (int) round($sh * $targetRatio);
        $x = (int) round(($sw - $cropW) / 2);
        $y = 0;
    } else {
        $cropW = $sw;
        $cropH = (int) round($sw / $targetRatio);
        $x = 0;
        $y = (int) round(($sh - $cropH) / 3); // iets hoger: gezicht
    }
    $outW = min($maxW, $cropW);
    $outH = (int) round($outW / $targetRatio);
    $dst = imagecreatetruecolor($outW, $outH);
    imagecopyresampled($dst, $src, 0, 0, $x, $y, $outW, $outH, $cropW, $cropH);
    return $dst;
}

$wide = $root . '/korne-pot-bookshelf-wide.png';
$close = $root . '/korne-pot-bookshelf-close.png';
$office = $root . '/korne-pot-office.png';

$srcLandscape = load_image($wide);
$land = crop_ratio($srcLandscape, 3, 2, 1800);
save_jpeg($land, $root . '/korne-pot-landscape.jpg');
imagedestroy($land);
imagedestroy($srcLandscape);
echo "landscape ok\n";

$srcPortrait = load_image(is_file($office) ? $office : $close);
$port = crop_ratio($srcPortrait, 2, 3, 1200);
save_jpeg($port, $root . '/korne-pot-portrait.jpg');
imagedestroy($port);
imagedestroy($srcPortrait);
echo "portrait ok\n";

$srcSquare = load_image($close);
$sq = crop_ratio($srcSquare, 1, 1, 1080);
save_jpeg($sq, $root . '/korne-pot-square.jpg');
imagedestroy($sq);
imagedestroy($srcSquare);
echo "square ok\n";

// Homepage avatar: copy square also to assets/img
$imgDir = dirname(__DIR__) . '/assets/img';
if (!is_dir($imgDir)) {
    mkdir($imgDir, 0755, true);
}
copy($root . '/korne-pot-square.jpg', $imgDir . '/korne-pot-author.jpg');
echo "homepage author ok\n";
