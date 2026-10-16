<?php
declare(strict_types=1);

namespace App\Services;

use Fpdf\Fpdf;

/**
 * FPDF with the few extras the barangay documents need: rotated text (the
 * PREVIEW watermark), images from bytes (signatures stored in the database),
 * and justified paragraphs with **bold** and ^^superscript^^ runs.
 *
 * FPDF's core fonts are Windows-1252, so every string goes through enc().
 */
final class BarangayPdf extends Fpdf
{
    private int $tmpCount = 0;

    public static function enc(string $text): string
    {
        $out = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);

        return $out === false ? (string) mb_convert_encoding($text, 'Windows-1252', 'UTF-8') : $out;
    }

    /** Text rotated around (x, y), in degrees counter-clockwise. */
    public function rotatedText(float $x, float $y, string $text, float $angle): void
    {
        $rad = deg2rad($angle);
        $c   = cos($rad);
        $s   = sin($rad);
        $cx  = $x * $this->k;
        $cy  = ($this->h - $y) * $this->k;
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        $this->Text($x, $y, self::enc($text));
        $this->_out('Q');
    }

    /** A filled diamond centered on (x, y). */
    public function diamond(float $x, float $y, float $r): void
    {
        $k = $this->k;
        $H = $this->h;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l %.2F %.2F l %.2F %.2F l h f',
            $x * $k, ($H - $y + $r) * $k, ($x + $r) * $k, ($H - $y) * $k,
            $x * $k, ($H - $y - $r) * $k, ($x - $r) * $k, ($H - $y) * $k));
    }

    /** Place an image held in memory (e.g. a signature from the settings table). */
    public function imageFromBytes(string $bytes, string $mime, float $x, float $y, float $w = 0, float $h = 0): void
    {
        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        $tmp = tempnam(sys_get_temp_dir(), 'bgimg') . '.' . $ext;
        file_put_contents($tmp, $bytes);
        try {
            $this->Image($tmp, $x, $y, $w, $h, strtoupper($ext === 'jpg' ? 'JPG' : 'PNG'));
        } finally {
            @unlink($tmp);
        }
        $this->tmpCount++;
    }

    /**
     * A justified paragraph with inline **bold** and ^^superscript^^.
     * Returns the Y after the paragraph.
     */
    public function richParagraph(string $text, float $x, float $y, float $width, string $family, float $size, float $lineHeight, float $indent = 0.0, string $align = 'J'): float
    {
        $words = $this->words($text);
        $space = $this->measure([[' ', false, false]], $family, $size);
        $lines = [];
        $line  = [];
        $lineW = 0.0;
        $avail = $width - $indent;

        foreach ($words as $word) {
            $w = $this->measure($word, $family, $size);
            $need = $line === [] ? $w : $lineW + $space + $w;
            if ($line !== [] && $need > $avail) {
                $lines[] = [$line, $lineW, $avail];
                $line  = [];
                $lineW = 0.0;
                $avail = $width;
                $need  = $w;
            }
            $line[] = [$word, $w];
            $lineW  = $need;
        }
        if ($line !== []) {
            $lines[] = [$line, $lineW, $avail];
        }

        foreach ($lines as $i => [$items, $used, $avail]) {
            $last  = $i === count($lines) - 1;
            $gaps  = count($items) - 1;
            $gap   = $space;
            $cx    = $x + ($i === 0 ? $indent : 0);
            if ($align === 'J' && !$last && $gaps > 0) {
                $gap = $space + ($avail - $used) / $gaps;
            } elseif ($align === 'C') {
                $cx += ($avail - $used) / 2;
            }
            foreach ($items as [$word, $w]) {
                $this->drawWord($word, $cx, $y, $family, $size);
                $cx += $w + $gap;
            }
            $y += $lineHeight;
        }

        return $y;
    }

    /** @return list<list<array{0:string,1:bool,2:bool}>> words made of (text, bold, superscript) segments */
    private function words(string $text): array
    {
        $runs  = [];
        $bold  = false;
        $sup   = false;
        foreach (preg_split('/(\*\*|\^\^)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if ($part === '**') { $bold = !$bold; continue; }
            if ($part === '^^') { $sup = !$sup; continue; }
            if ($part !== '') {
                $runs[] = [$part, $bold, $sup];
            }
        }

        $words = [];
        $word  = [];
        foreach ($runs as [$chunk, $b, $s]) {
            $pieces = preg_split('/(\s+)/', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
            foreach ($pieces as $piece) {
                if ($piece === '') {
                    continue;
                }
                if (trim($piece) === '') {
                    if ($word !== []) { $words[] = $word; $word = []; }
                    continue;
                }
                $word[] = [$piece, $b, $s];
            }
        }
        if ($word !== []) {
            $words[] = $word;
        }

        return $words;
    }

    private function measure(array $word, string $family, float $size): float
    {
        $w = 0.0;
        foreach ($word as [$t, $b, $s]) {
            $this->SetFont($family, $b ? 'B' : '', $s ? $size * 0.62 : $size);
            $w += $this->GetStringWidth(self::enc($t));
        }

        return $w;
    }

    private function drawWord(array $word, float $x, float $y, string $family, float $size): void
    {
        foreach ($word as [$t, $b, $s]) {
            $fs = $s ? $size * 0.62 : $size;
            $this->SetFont($family, $b ? 'B' : '', $fs);
            $this->Text($x, $y - ($s ? $size * 0.16 : 0), self::enc($t));
            $x += $this->GetStringWidth(self::enc($t));
        }
    }
}
