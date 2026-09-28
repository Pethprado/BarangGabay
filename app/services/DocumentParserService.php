<?php
declare(strict_types=1);

namespace App\Services;

use ZipArchive;

/**
 * Service to parse uploaded .docx, .pdf, and .csv document files
 * and extract structured Manobo vocabulary entries for preview and import.
 */
class DocumentParserService
{
    /**
     * Parse an uploaded file and extract candidate vocabulary entries.
     *
     * @param string $filePath Absolute path to temporary/uploaded file
     * @param string $originalName Original filename
     * @return array{
     *     success: bool,
     *     filename: string,
     *     file_type: string,
     *     entries: list<array{
     *         manobo: string,
     *         english: string,
     *         tagalog: string,
     *         bisaya: string,
     *         category: string,
     *         part_of_speech: string,
     *         notes: string,
     *         confidence: string,
     *         needs_review: bool,
     *         raw_source: string
     *     }>,
     *     error: string|null
     * }
     */
    public function parseFile(string $filePath, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        switch ($ext) {
            case 'docx':
                return $this->parseDocx($filePath, $originalName);
            case 'pdf':
                return $this->parsePdf($filePath, $originalName);
            case 'csv':
            case 'txt':
                return $this->parseCsv($filePath, $originalName);
            default:
                return [
                    'success'   => false,
                    'filename'  => $originalName,
                    'file_type' => $ext,
                    'entries'   => [],
                    'error'     => 'Unsupported file format. Please upload a .docx, .pdf, or .csv file.',
                ];
        }
    }

    /**
     * Parse Microsoft Word .docx document.
     */
    public function parseDocx(string $filePath, string $originalName): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [
                'success'   => false,
                'filename'  => $originalName,
                'file_type' => 'docx',
                'entries'   => [],
                'error'     => 'Failed to open DOCX zip package.',
            ];
        }

        $xmlContent = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xmlContent) {
            return [
                'success'   => false,
                'filename'  => $originalName,
                'file_type' => 'docx',
                'entries'   => [],
                'error'     => 'Invalid DOCX structure: word/document.xml missing.',
            ];
        }

        // Clean namespaces for easier SimpleXML parsing
        $xmlContent = preg_replace('/xmlns:[^=]+="[^"]+"/', '', $xmlContent);
        $xmlContent = preg_replace('/<(\/)?w:/', '<$1', $xmlContent);

        $xml = @simplexml_load_string($xmlContent);
        if (!$xml) {
            return [
                'success'   => false,
                'filename'  => $originalName,
                'file_type' => 'docx',
                'entries'   => [],
                'error'     => 'Failed to parse DOCX XML content.',
            ];
        }

        $entries = [];

        // 1. Extract from Tables (<tr/tc>)
        if (isset($xml->body->tbl)) {
            foreach ($xml->body->tbl as $tbl) {
                foreach ($tbl->tr as $tr) {
                    $cells = [];
                    foreach ($tr->tc as $tc) {
                        $cellText = '';
                        foreach ($tc->p as $p) {
                            $pText = '';
                            foreach ($p->r as $r) {
                                $pText .= (string) $r->t;
                            }
                            $cellText .= ' ' . $pText;
                        }
                        $cells[] = trim($cellText);
                    }

                    $parsed = $this->interpretColumns($cells);
                    if ($parsed !== null) {
                        $entries[] = $parsed;
                    }
                }
            }
        }

        // 2. Extract from Paragraphs (<p>)
        if (isset($xml->body->p)) {
            foreach ($xml->body->p as $p) {
                $pText = '';
                foreach ($p->r as $r) {
                    $pText .= (string) $r->t;
                }
                $pText = trim($pText);
                if ($pText !== '') {
                    $parsed = $this->interpretLine($pText);
                    if ($parsed !== null) {
                        $entries[] = $parsed;
                    }
                }
            }
        }

        return [
            'success'   => true,
            'filename'  => $originalName,
            'file_type' => 'docx',
            'entries'   => $this->deduplicateExtracted($entries),
            'error'     => null,
        ];
    }

    /**
     * Parse PDF document using text stream extraction + Claude AI fallback for scanned/complex layouts.
     */
    public function parsePdf(string $filePath, string $originalName): array
    {
        $text = $this->extractRawPdfText($filePath);

        $entries = [];

        if (strlen(trim($text)) > 20) {
            // Process extracted plain text line by line
            $lines = preg_split('/(\r\n|\r|\n)/', $text);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $parsed = $this->interpretLine($line);
                if ($parsed !== null) {
                    $entries[] = $parsed;
                }
            }
        }

        // Fallback: If native text extraction yielded few entries, use AIService to parse document structure
        if (count($entries) < 2 && !empty(env('ANTHROPIC_API_KEY', ''))) {
            try {
                $ai = new AIService();
                $aiEntries = $ai->extractVocabularyFromText($text !== '' ? $text : file_get_contents($filePath));
                foreach ($aiEntries as $ae) {
                    $entries[] = [
                        'manobo'         => trim((string) ($ae['manobo'] ?? '')),
                        'english'        => trim((string) ($ae['english'] ?? '')),
                        'tagalog'        => trim((string) ($ae['tagalog'] ?? $ae['filipino'] ?? '')),
                        'bisaya'         => trim((string) ($ae['bisaya'] ?? '')),
                        'category'       => trim((string) ($ae['category'] ?? 'general')),
                        'part_of_speech' => trim((string) ($ae['part_of_speech'] ?? 'word')),
                        'notes'          => trim((string) ($ae['notes'] ?? '')),
                        'confidence'     => 'high',
                        'needs_review'   => !empty($ae['needs_review']),
                        'raw_source'     => 'AI PDF Extraction',
                    ];
                }
            } catch (\Throwable $e) {
                error_log('[DocumentParserService] AI PDF extraction fallback failed: ' . $e->getMessage());
            }
        }

        return [
            'success'   => true,
            'filename'  => $originalName,
            'file_type' => 'pdf',
            'entries'   => $this->deduplicateExtracted($entries),
            'error'     => null,
        ];
    }

    /**
     * Parse CSV or plain text line file.
     */
    public function parseCsv(string $filePath, string $originalName): array
    {
        $handle = @fopen($filePath, 'r');
        if (!$handle) {
            return [
                'success'   => false,
                'filename'  => $originalName,
                'file_type' => 'csv',
                'entries'   => [],
                'error'     => 'Failed to open file.',
            ];
        }

        $entries = [];
        $header = null;

        while (($row = fgetcsv($handle, 2048, ',')) !== false) {
            if (empty(array_filter($row))) continue;

            if ($header === null && (str_contains(strtolower($row[0] ?? ''), 'manobo') || str_contains(strtolower($row[0] ?? ''), 'word'))) {
                $header = array_map('strtolower', array_map('trim', $row));
                continue;
            }

            $parsed = $this->interpretColumns($row, $header);
            if ($parsed !== null) {
                $entries[] = $parsed;
            }
        }
        fclose($handle);

        return [
            'success'   => true,
            'filename'  => $originalName,
            'file_type' => 'csv',
            'entries'   => $this->deduplicateExtracted($entries),
            'error'     => null,
        ];
    }

    /**
     * Interpret a set of table column values.
     */
    private function interpretColumns(array $cols, ?array $header = null): ?array
    {
        $cols = array_values(array_filter(array_map('trim', $cols), fn($c) => $c !== ''));
        $count = count($cols);

        if ($count < 2) return null;

        // Header check
        $firstLower = strtolower($cols[0]);
        if (in_array($firstLower, ['manobo', 'word', 'headword', 'term', 'salita'], true)) {
            return null;
        }

        $manobo = $cols[0];
        $tagalog = '';
        $english = '';
        $bisaya = '';

        if ($header !== null) {
            $engIdx = array_search('english', $header, true);
            $tagIdx = array_search('tagalog', $header, true);
            if ($tagIdx === false) $tagIdx = array_search('filipino', $header, true);
            $bisIdx = array_search('bisaya', $header, true);
            if ($bisIdx === false) $bisIdx = array_search('cebuano', $header, true);

            if ($engIdx !== false && isset($cols[$engIdx])) $english = $cols[$engIdx];
            if ($tagIdx !== false && isset($cols[$tagIdx])) $tagalog = $cols[$tagIdx];
            if ($bisIdx !== false && isset($cols[$bisIdx])) $bisaya  = $cols[$bisIdx];
        }

        if ($english === '' && $tagalog === '') {
            if ($count >= 4) {
                $tagalog = $cols[1];
                $english = $cols[2];
                $bisaya  = $cols[3];
            } elseif ($count === 3) {
                $tagalog = $cols[1];
                $english = $cols[2];
            } else {
                $english = $cols[1];
                $tagalog = $cols[1];
            }
        }

        if (strlen($manobo) < 1) return null;

        return [
            'manobo'         => $manobo,
            'english'        => $english,
            'tagalog'        => $tagalog,
            'bisaya'         => $bisaya,
            'category'       => 'general',
            'part_of_speech' => str_contains($manobo, ' ') ? 'phrase' : 'word',
            'notes'          => '',
            'confidence'     => 'high',
            'needs_review'   => false,
            'raw_source'     => implode(' | ', $cols),
        ];
    }

    /**
     * Interpret a single line of text formatted as a delimiter list or word-meaning pair.
     * e.g. "Badyaw - Malawak - Wide" or "Badyaw : wide / malawak"
     */
    private function interpretLine(string $line): ?array
    {
        if (strlen($line) < 3) return null;

        // Skip headers / titles
        $lower = strtolower($line);
        if (str_starts_with($lower, 'page ') || str_contains($lower, 'table of contents') || str_contains($lower, 'manobo dictionary')) {
            return null;
        }

        // Try splitting by common delimiters: tab, double space, hyphen, colon, equals
        $parts = preg_split('/\s*(\t|--|---|–|—|-|:|=)\s*/u', $line);
        if ($parts !== false && count($parts) >= 2) {
            return $this->interpretColumns($parts);
        }

        return null;
    }

    /**
     * Extract raw text from PDF file.
     */
    private function extractRawPdfText(string $filePath): string
    {
        $content = @file_get_contents($filePath);
        if (!$content) return '';

        $text = '';

        // Extract text inside PDF stream objects
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                // Try decompressing gz/flate streams
                $decompressed = @gzuncompress($stream);
                if (!$decompressed) {
                    $decompressed = @gzinflate(substr($stream, 2));
                }
                $target = $decompressed !== false ? $decompressed : $stream;

                // Extract text operators (Tj / TJ / ')
                if (preg_match_all('/\((.*?)\)\s*Tj/s', $target, $tj)) {
                    $text .= ' ' . implode(' ', $tj[1]);
                }
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $target, $tj2)) {
                    foreach ($tj2[1] as $chunk) {
                        if (preg_match_all('/\((.*?)\)/s', $chunk, $m)) {
                            $text .= ' ' . implode('', $m[1]);
                        }
                    }
                }
            }
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Deduplicate entries extracted in a single file parse.
     */
    private function deduplicateExtracted(array $entries): array
    {
        $seen = [];
        $unique = [];

        foreach ($entries as $e) {
            $key = strtolower(trim($e['manobo']));
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = $e;
        }

        return $unique;
    }
}
