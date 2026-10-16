<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Turns an uploaded signature (often a large phone photo or scan on white
 * paper) into a small transparent PNG of just the ink: scaled down, empty
 * margins trimmed, paper-white made transparent. Stored that way, it prints
 * full size on documents without covering the text beneath it.
 */
final class SignatureImage
{
    /** Longest side after scaling; plenty for a 18 mm-high signature. */
    private const MAX_SIDE = 1200;

    /** PNG bytes of the cleaned signature, or null when it is not a usable image. */
    public static function clean(string $bytes): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $im = @imagecreatefromstring($bytes);
        if ($im === false) {
            return null;
        }
        imagesavealpha($im, true);

        // 1. Scale down large photos first (faster, smaller to store).
        $w = imagesx($im);
        $h = imagesy($im);
        $scale = min(1.0, self::MAX_SIDE / max($w, $h));
        if ($scale < 1.0) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $small = imagecreatetruecolor($nw, $nh);
            imagealphablending($small, false);
            imagesavealpha($small, true);
            imagefill($small, 0, 0, imagecolorallocatealpha($small, 255, 255, 255, 127));
            imagecopyresampled($small, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $im = $small;
            $w  = $nw;
            $h  = $nh;
        }

        // 2. Ink only: paper-white and transparent pixels become transparent;
        //    remember where the ink is to trim the margins.
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $clear = imagecolorallocatealpha($out, 255, 255, 255, 127);
        [$minX, $minY, $maxX, $maxY] = [$w, $h, -1, -1];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorsforindex($im, imagecolorat($im, $x, $y));
                if ($c['alpha'] >= 110 || ($c['red'] > 215 && $c['green'] > 215 && $c['blue'] > 215)) {
                    imagesetpixel($out, $x, $y, $clear);
                    continue;
                }
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $c['red'], $c['green'], $c['blue'], $c['alpha']));
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
        if ($maxX < 0 || ($maxX - $minX) < 10 || ($maxY - $minY) < 5) {
            return null;   // blank page: no ink found
        }

        // 3. Trim to the ink (a few pixels of breathing room).
        $pad  = 6;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $maxX = min($w - 1, $maxX + $pad);
        $maxY = min($h - 1, $maxY + $pad);
        $trim = imagecrop($out, ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX + 1, 'height' => $maxY - $minY + 1]);
        if ($trim === false) {
            return null;
        }
        imagealphablending($trim, false);
        imagesavealpha($trim, true);

        ob_start();
        imagepng($trim, null, 9);

        return (string) ob_get_clean();
    }
}
