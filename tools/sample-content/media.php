<?php
declare(strict_types=1);

/**
 * Generated cover images and PDFs for the sample content seeder.
 *
 * Everything here is drawn locally and written as raw bytes. No file is
 * downloaded, and no photograph of a real person is ever used — which
 * sidesteps licensing and privacy entirely, and means the seeder works on a
 * machine with no network and no image library.
 *
 * Every generated file states that it is sample content, in the image itself.
 * A seeded storm-surge advisory on a government notice board must not be
 * mistakable for a real one at a glance, and a filename nobody reads is not
 * enough.
 *
 * The drawing primitives are in png.php rather than GD: GD ships with PHP but
 * is commented out in this project's own XAMPP php.ini, and a seeder that
 * needs a system configuration change before it will run is one that does not
 * get run.
 */

require_once __DIR__ . '/png.php';

/** Barangay-appropriate colour blocks: deep sea, storm, forest, earth, slate. */
const SAMPLE_PALETTE = [
    'sea'    => [[14, 63, 94],  [26, 118, 150]],
    'storm'  => [[91, 33, 33],  [152, 66, 52]],
    'forest' => [[19, 74, 52],  [36, 124, 80]],
    'earth'  => [[92, 63, 25],  [148, 108, 44]],
    'slate'  => [[38, 46, 62],  [76, 88, 110]],
];

/**
 * Draw a cover image: a two-tone barangay colour block, the post's title, and
 * a diagonal SAMPLE wash that cannot be cropped out of the thumbnail.
 *
 * Returns raw PNG bytes so the caller can hand them to FileService and take
 * the ordinary upload path — MIME sniffing, randomised filename, the lot.
 *
 * 1200x630 is the standard open-graph cover ratio, which is what the card and
 * detail templates are laid out for.
 */
function sample_cover_png(string $title, string $paletteKey, string $kicker): string
{
    [$from, $to] = SAMPLE_PALETTE[$paletteKey] ?? SAMPLE_PALETTE['slate'];

    $w = 1200;
    $h = 630;
    $c = png_canvas($w, $h);

    // Vertical gradient. Cheap, and it stops the block reading as a broken
    // image placeholder the way a flat fill does.
    for ($y = 0; $y < $h; $y++) {
        $t = $y / max(1, $h - 1);
        $row = [
            (int) round($from[0] + ($to[0] - $from[0]) * $t),
            (int) round($from[1] + ($to[1] - $from[1]) * $t),
            (int) round($from[2] + ($to[2] - $from[2]) * $t),
        ];
        png_rect($c, 0, $y, $w - 1, $y, $row);
    }

    // Diagonal SAMPLE wash, drawn before the text so the text stays readable.
    for ($y = 0; $y < $h; $y++) {
        for ($x = ($y % 170); $x < $w; $x += 170) {
            for ($k = 0; $k < 34; $k++) {
                png_wash($c, $x + $k, $y, [255, 255, 255], 0.055);
            }
        }
    }

    $white = [255, 255, 255];
    $amber = [251, 191, 36];
    $ink   = [17, 24, 39];

    // Kicker strip along the top — the category or post type.
    for ($y = 0; $y < 84; $y++) {
        for ($x = 0; $x < $w; $x++) {
            png_wash($c, $x, $y, [0, 0, 0], 0.32);
        }
    }
    png_text($c, $kicker, 48, 30, 3, $amber);

    // Title, wrapped by hand. Scale 5 gives ~30px caps, readable on a card.
    $lines = sample_wrap($title, 40);
    $y     = 190;
    foreach (array_slice($lines, 0, 5) as $line) {
        png_text($c, $line, 48, $y, 4, $white);
        $y += 56;
    }

    // Unmistakable footer band. This is the part that has to survive being
    // seen at thumbnail size.
    png_rect($c, 0, $h - 104, $w - 1, $h - 1, $amber);
    png_text($c, 'SAMPLE CONTENT - NOT A REAL NOTICE', 48, $h - 84, 4, $ink);
    png_text($c, 'BarangGabay test data - Barangay Bayogo, Madrid', 48, $h - 38, 2, $ink);

    return png_encode($c);
}

/** Wrap on word boundaries, never mid-word. @return list<string> */
function sample_wrap(string $text, int $width): array
{
    // Covers are drawn with an ASCII bitmap font, so anything outside that
    // range is transliterated rather than dropped as a blank. The stored post
    // title keeps its real characters; only the picture is folded.
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text  = is_string($ascii) && trim($ascii) !== '' ? $ascii : $text;

    return explode("\n", wordwrap(trim($text), $width, "\n", true));
}

/**
 * A minimal, valid single-page PDF.
 *
 * Hand-assembled rather than pulled from a library: the project has no PDF
 * writer as a dependency, and adding one to generate a page of obviously fake
 * text would be a poor trade. This produces a real PDF — finfo reports
 * application/pdf, so it passes FileService's MIME check the same way a staff
 * upload does, and PDF.js renders it on the ordinance page.
 *
 * @param list<string> $lines Body lines, already short enough for the page.
 */
function sample_pdf(string $title, array $lines): string
{
    $esc = static function (string $s): string {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            is_string($ascii) && $ascii !== '' ? $ascii : $s
        );
    };

    // Page content: a red SAMPLE banner, the title, then the body lines.
    $content  = "BT /F1 18 Tf 0.8 0.1 0.1 rg 56 745 Td (SAMPLE - NOT A REAL ORDINANCE) Tj ET\n";

    $y = 712;
    foreach (sample_wrap($title, 74) as $titleLine) {
        $content .= "BT /F1 13 Tf 0 0 0 rg 56 {$y} Td (" . $esc($titleLine) . ") Tj ET\n";
        $y -= 18;
    }

    $y -= 12;
    foreach ($lines as $line) {
        $content .= "BT /F1 10 Tf 0.1 0.1 0.1 rg 56 {$y} Td (" . $esc($line) . ") Tj ET\n";
        $y -= 15;
        if ($y < 80) {
            break;
        }
    }

    $content .= "BT /F1 9 Tf 0.45 0.45 0.45 rg 56 54 Td "
        . "(Generated by tools/seed-sample-content.php for testing. Remove with --purge.) Tj ET\n";

    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
            . '/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf     = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $i => $object) {
        $offsets[$i + 1] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xrefAt = strlen($pdf);
    $count  = count($objects) + 1;

    $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
    for ($i = 1; $i < $count; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefAt}\n%%EOF\n";

    return $pdf;
}
