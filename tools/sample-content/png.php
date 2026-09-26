<?php
declare(strict_types=1);

/**
 * A minimal PNG writer and bitmap font, for when GD is not available.
 *
 * GD ships with PHP but is commented out in plenty of installs — it is
 * `;extension=gd` in this project's own XAMPP php.ini — and a seeder that
 * cannot run until someone edits a system configuration file is a seeder that
 * does not get run. Enabling GD is a one-line change the operator can make,
 * but it is theirs to make, not this script's, and it needs an Apache restart.
 *
 * So the cover generator prefers GD when it is loaded and falls back to this.
 * The output is a real PNG: finfo reports image/png, so it passes
 * FileService's MIME check exactly as a staff member's upload does.
 *
 * Deliberately small in scope. This draws filled rectangles and uppercase
 * text, which is all a sample cover needs — it is not, and should not grow
 * into, an image library.
 */

/** A 5x7 bitmap font. Each glyph is 5 column bytes; bit 0 is the top row. */
const PNG_FONT = [
    ' ' => [0x00,0x00,0x00,0x00,0x00], '!' => [0x00,0x00,0x5F,0x00,0x00],
    '"' => [0x00,0x07,0x00,0x07,0x00], '#' => [0x14,0x7F,0x14,0x7F,0x14],
    '%' => [0x23,0x13,0x08,0x64,0x62], '&' => [0x36,0x49,0x55,0x22,0x50],
    "'" => [0x00,0x05,0x03,0x00,0x00], '(' => [0x00,0x1C,0x22,0x41,0x00],
    ')' => [0x00,0x41,0x22,0x1C,0x00], '+' => [0x08,0x08,0x3E,0x08,0x08],
    ',' => [0x00,0x50,0x30,0x00,0x00], '-' => [0x08,0x08,0x08,0x08,0x08],
    '.' => [0x00,0x60,0x60,0x00,0x00], '/' => [0x20,0x10,0x08,0x04,0x02],
    '0' => [0x3E,0x51,0x49,0x45,0x3E], '1' => [0x00,0x42,0x7F,0x40,0x00],
    '2' => [0x42,0x61,0x51,0x49,0x46], '3' => [0x21,0x41,0x45,0x4B,0x31],
    '4' => [0x18,0x14,0x12,0x7F,0x10], '5' => [0x27,0x45,0x45,0x45,0x39],
    '6' => [0x3C,0x4A,0x49,0x49,0x30], '7' => [0x01,0x71,0x09,0x05,0x03],
    '8' => [0x36,0x49,0x49,0x49,0x36], '9' => [0x06,0x49,0x49,0x29,0x1E],
    ':' => [0x00,0x36,0x36,0x00,0x00], ';' => [0x00,0x56,0x36,0x00,0x00],
    '=' => [0x14,0x14,0x14,0x14,0x14], '?' => [0x02,0x01,0x51,0x09,0x06],
    'A' => [0x7E,0x11,0x11,0x11,0x7E], 'B' => [0x7F,0x49,0x49,0x49,0x36],
    'C' => [0x3E,0x41,0x41,0x41,0x22], 'D' => [0x7F,0x41,0x41,0x22,0x1C],
    'E' => [0x7F,0x49,0x49,0x49,0x41], 'F' => [0x7F,0x09,0x09,0x01,0x01],
    'G' => [0x3E,0x41,0x49,0x49,0x7A], 'H' => [0x7F,0x08,0x08,0x08,0x7F],
    'I' => [0x00,0x41,0x7F,0x41,0x00], 'J' => [0x20,0x40,0x41,0x3F,0x01],
    'K' => [0x7F,0x08,0x14,0x22,0x41], 'L' => [0x7F,0x40,0x40,0x40,0x40],
    'M' => [0x7F,0x02,0x04,0x02,0x7F], 'N' => [0x7F,0x04,0x08,0x10,0x7F],
    'O' => [0x3E,0x41,0x41,0x41,0x3E], 'P' => [0x7F,0x09,0x09,0x09,0x06],
    'Q' => [0x3E,0x41,0x51,0x21,0x5E], 'R' => [0x7F,0x09,0x19,0x29,0x46],
    'S' => [0x46,0x49,0x49,0x49,0x31], 'T' => [0x01,0x01,0x7F,0x01,0x01],
    'U' => [0x3F,0x40,0x40,0x40,0x3F], 'V' => [0x1F,0x20,0x40,0x20,0x1F],
    'W' => [0x7F,0x20,0x18,0x20,0x7F], 'X' => [0x63,0x14,0x08,0x14,0x63],
    'Y' => [0x03,0x04,0x78,0x04,0x03], 'Z' => [0x61,0x51,0x49,0x45,0x43],
];

/**
 * A mutable RGB canvas: a flat array of [r,g,b] rows.
 *
 * Plain arrays rather than a class — this exists for the length of one script
 * run and nothing else ever touches it.
 */
function png_canvas(int $w, int $h): array
{
    return ['w' => $w, 'h' => $h, 'px' => array_fill(0, $w * $h * 3, 0)];
}

function png_set(array &$c, int $x, int $y, array $rgb): void
{
    if ($x < 0 || $y < 0 || $x >= $c['w'] || $y >= $c['h']) {
        return;
    }
    $i = ($y * $c['w'] + $x) * 3;
    $c['px'][$i]     = $rgb[0];
    $c['px'][$i + 1] = $rgb[1];
    $c['px'][$i + 2] = $rgb[2];
}

function png_rect(array &$c, int $x0, int $y0, int $x1, int $y1, array $rgb): void
{
    for ($y = max(0, $y0); $y <= min($c['h'] - 1, $y1); $y++) {
        for ($x = max(0, $x0); $x <= min($c['w'] - 1, $x1); $x++) {
            png_set($c, $x, $y, $rgb);
        }
    }
}

/** Blend a colour over what is already there, at the given alpha (0..1). */
function png_wash(array &$c, int $x, int $y, array $rgb, float $alpha): void
{
    if ($x < 0 || $y < 0 || $x >= $c['w'] || $y >= $c['h']) {
        return;
    }
    $i = ($y * $c['w'] + $x) * 3;
    for ($k = 0; $k < 3; $k++) {
        $c['px'][$i + $k] = (int) round($rgb[$k] * $alpha + $c['px'][$i + $k] * (1 - $alpha));
    }
}

/**
 * Draw uppercase text at a given pixel scale.
 *
 * Characters outside the font are transliterated to ASCII first and dropped if
 * they still do not map — a cover is a picture, and a missing glyph there is
 * cosmetic, while the stored title keeps every character it was written with.
 */
function png_text(array &$c, string $text, int $x, int $y, int $scale, array $rgb): void
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text  = strtoupper(is_string($ascii) && $ascii !== '' ? $ascii : $text);

    $cursor = $x;
    $len    = strlen($text);

    for ($i = 0; $i < $len; $i++) {
        $glyph = PNG_FONT[$text[$i]] ?? null;

        if ($glyph !== null) {
            foreach ($glyph as $col => $bits) {
                for ($row = 0; $row < 7; $row++) {
                    if (($bits >> $row) & 1) {
                        png_rect(
                            $c,
                            $cursor + $col * $scale, $y + $row * $scale,
                            $cursor + ($col + 1) * $scale - 1, $y + ($row + 1) * $scale - 1,
                            $rgb
                        );
                    }
                }
            }
        }

        $cursor += 6 * $scale;   // 5 columns + 1 of spacing
    }
}

/** Width in pixels that png_text() would occupy. */
function png_text_width(string $text, int $scale): int
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    return strlen(is_string($ascii) && $ascii !== '' ? $ascii : $text) * 6 * $scale;
}

/**
 * Encode the canvas as PNG bytes.
 *
 * Truecolour, 8 bits per channel, no interlacing, filter type 0 on every
 * scanline — the simplest encoding the format allows, which is all that is
 * needed and the least that can go wrong.
 */
function png_encode(array $c): string
{
    $raw = '';
    for ($y = 0; $y < $c['h']; $y++) {
        $raw .= "\x00";                                  // filter: none
        $off  = $y * $c['w'] * 3;
        for ($i = 0; $i < $c['w'] * 3; $i++) {
            $raw .= chr($c['px'][$off + $i] & 0xFF);
        }
    }

    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };

    $ihdr = pack('NN', $c['w'], $c['h']) . "\x08\x02\x00\x00\x00";

    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', $ihdr)
        . $chunk('IDAT', gzcompress($raw, 6))
        . $chunk('IEND', '');
}
