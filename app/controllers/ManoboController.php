<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Services\BisayaDictionary;
use App\Services\ManoboAutoTranslator;
use App\Services\ManoboDictionary;

/**
 * Admin curation screen for the Manobo AND Bisaya dictionaries, plus the
 * resident-facing dictionary page and the "MN" auto-translate endpoint.
 *
 * CHANGED: both dictionaries moved from CSV files to the manobo_dictionary /
 * bisaya_dictionary tables (migration 030) — see ManoboDictionary's and
 * BisayaDictionary's own docblocks. Every write still goes through the
 * dictionary service, which is now what keeps a Trash (soft delete) instead
 * of removing a row outright.
 *
 * CHANGED: store/update/delete are now ['auth', 'role:admin,staff'] rather
 * than admin-only — this was explicitly requested (staff should be able to
 * add, edit and delete/trash words); restoring from Trash and purging
 * permanently stay admin-only, matching how irreversible actions are gated
 * elsewhere in this app.
 */
class ManoboController
{
    private ManoboDictionary $dictionary;
    private BisayaDictionary $bisaya;

    public function __construct()
    {
        $this->dictionary = new ManoboDictionary();
        $this->bisaya      = new BisayaDictionary();
    }

    // ── Pages ─────────────────────────────────────────────────────────────

    /**
     * GET /manobo
     *
     * Read-only dictionary for residents — a lookup tool for anyone who does
     * not fully read Manobo, in either direction (Manobo → English /
     * Tagalog, or the reverse).
     *
     * Deliberately separate from adminIndex(): this one cannot add, edit or
     * delete anything, and it says plainly how many words the dataset holds so
     * a resident is never left guessing whether a blank result means "no such
     * word" or "not collected yet".
     */
    public function residentIndex(): void
    {
        $search   = trim($_GET['q']        ?? '');
        $category = trim($_GET['category'] ?? '');

        try {
            $entries    = ($search !== '' || $category !== '')
                ? $this->dictionary->search($search, $category)
                : $this->dictionary->all();
            $categories = $this->dictionary->categories();
            $totalWords = count($this->dictionary->all());
        } catch (\Throwable $e) {
            // A missing or unreadable dataset must not take the page down —
            // the empty state already explains that no words are available.
            error_log('[ManoboController::residentIndex] ' . $e->getMessage());
            $entries = $categories = [];
            $totalWords = 0;
        }

        view('resident/manobo', compact('entries', 'categories', 'search', 'category', 'totalWords'));
    }

    /**
     * GET /admin/manobo
     * Entry list with search/filter, an add form, and a lookup try-it box.
     */
    public function adminIndex(): void
    {
        $search   = trim($_GET['q']        ?? '');
        $category = trim($_GET['category'] ?? '');
        $status   = trim($_GET['status']   ?? '');
        $tryTerm  = trim($_GET['try']      ?? '');
        $tryTo    = trim($_GET['to']       ?? 'english');

        $tryResult = null;
        if ($tryTerm !== '') {
            try {
                $tryResult = $this->dictionary->translate($tryTerm, $tryTo);
            } catch (\Throwable $e) {
                $tryResult = null;
            }
        }

        // Categories the barangay system needs but the dataset cannot yet fill.
        $coverage = [];
        foreach (['greetings', 'numbers', 'family', 'requests', 'farming', 'health', 'civic'] as $needed) {
            $coverage[$needed] = count($this->dictionary->byCategory($needed));
        }

        $allEntries = $this->dictionary->search($search, $category);
        if ($status !== '') {
            $entries = array_values(array_filter($allEntries, function ($e) use ($status) {
                if ($status === 'pending_review') {
                    return ($e['review_status'] ?? '') === 'pending_review' || !empty($e['needs_review']);
                }
                return ($e['review_status'] ?? '') === $status;
            }));
        } else {
            $entries = $allEntries;
        }

        // Fetch missing concepts
        $missingConcepts = [];
        try {
            $stmt = db()->query("SELECT * FROM manobo_missing_concepts ORDER BY usage_count DESC, last_seen_at DESC LIMIT 50");
            $missingConcepts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {}

        $dictVersion = (new \App\Services\ManoboHybridTranslator())->getDictionaryVersion();
        $importHistory = $this->dictionary->getImportHistory();

        view('admin/manobo/index', [
            'neededWords'       => $this->neededUiWords(),
            'entries'           => $entries,
            'totalEntries'      => $this->dictionary->count(),
            'categories'        => $this->dictionary->categories(),
            'partsOfSpeech'     => $this->dictionary->partsOfSpeech(),
            'coverage'          => $coverage,
            'search'            => $search,
            'filterCategory'    => $category,
            'filterStatus'      => $status,
            'tryTerm'           => $tryTerm,
            'tryTo'             => $tryTo,
            'tryResult'         => $tryResult,
            'needsVerification' => $this->dictionary->needsVerification(),
            'missingConcepts'   => $missingConcepts,
            'dictionaryVersion' => $dictVersion,
            'importHistory'     => $importHistory,
            'trash'             => $this->dictionary->trash(),
            'canRestore'        => in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true),
            'canDelete'         => in_array($_SESSION['role'] ?? '', ['admin', 'staff', 'superadmin'], true),
        ]);
    }

    /** POST /admin/manobo/approve — approve a pending dictionary entry. */
    public function approve(): void
    {
        check_csrf();
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $this->dictionary->approve($id, (int) ($_SESSION['user_id'] ?? 0));
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/manobo');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'manobo.entry_approved', 'Approved Manobo entry id ' . $id);
        flash('success', 'Naaprubahan ang salita sa opisyal na diksyunaryo.');
        redirect('/admin/manobo');
    }

    /** POST /admin/manobo/archive — archive a dictionary entry. */
    public function archive(): void
    {
        check_csrf();
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $this->dictionary->archive($id, (int) ($_SESSION['user_id'] ?? 0));
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/manobo');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'manobo.entry_archived', 'Archived Manobo entry id ' . $id);
        flash('success', 'Na-archive ang salita sa diksyunaryo.');
        redirect('/admin/manobo');
    }

    /** POST /admin/manobo/import-doc — parse uploaded .docx, .pdf, or .csv document. */
    public function importDoc(): void
    {
        check_csrf();

        $file = $_FILES['doc_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Pumili ng .docx, .pdf, o .csv file na i-upload.');
            redirect('/admin/manobo');
        }

        $filename = basename($file['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'pdf', 'csv', 'txt'], true)) {
            flash('error', 'Ang format ng file ay dapat .docx, .pdf, .csv, o .txt.');
            redirect('/admin/manobo');
        }

        try {
            $parsed = \App\Services\DocumentParserService::parseFile($file['tmp_name'], $filename);
            $rawEntries = $parsed['entries'] ?? [];

            if (empty($rawEntries)) {
                flash('error', 'Walang nabasang bokabularyo sa in-upload na dokumento: ' . ($parsed['error'] ?? 'Unreadable or empty document.'));
                redirect('/admin/manobo');
            }

            // Check each parsed entry for duplicates or conflicts in the dictionary
            $previewEntries = [];
            foreach ($rawEntries as $item) {
                $manobo  = trim($item['manobo'] ?? '');
                $english = trim($item['english'] ?? '');
                $tagalog = trim($item['tagalog'] ?? '');
                $bisaya  = trim($item['bisaya'] ?? '');

                if ($manobo === '' && $english === '' && $tagalog === '') {
                    continue;
                }

                $dup = $this->dictionary->findDuplicate($manobo, $english, $tagalog);
                
                $status = 'new';
                $existingId = null;
                $dupDetail = null;

                if ($dup !== null) {
                    $existingId = (int)$dup['id'];
                    $dupDetail = $dup;
                    if (mb_strtolower(trim($dup['manobo'])) === mb_strtolower($manobo) &&
                        (mb_strtolower(trim($dup['english'])) === mb_strtolower($english) || mb_strtolower(trim($dup['tagalog'])) === mb_strtolower($tagalog))) {
                        $status = 'exact_duplicate';
                    } else {
                        $status = 'conflicting_meaning';
                    }
                }

                $previewEntries[] = [
                    'manobo'          => $manobo,
                    'english'         => $english,
                    'tagalog'         => $tagalog,
                    'bisaya'          => $bisaya,
                    'category'        => $item['category'] ?? 'general',
                    'part_of_speech'  => $item['part_of_speech'] ?? 'noun',
                    'notes'           => $item['notes'] ?? '',
                    'source_page'     => $item['source_page'] ?? null,
                    'status'          => $status,
                    'existing_id'     => $existingId,
                    'existing_entry'  => $dupDetail,
                    'action'          => $status === 'exact_duplicate' ? 'skip' : ($status === 'conflicting_meaning' ? 'separate' : 'import'),
                ];
            }

            $_SESSION['dictionary_import_preview'] = [
                'filename'        => $filename,
                'entries'         => $previewEntries,
                'unclear_count'   => $parsed['unclear_count'] ?? 0,
                'needs_review'    => $parsed['needs_review'] ?? false,
            ];

            redirect('/admin/manobo/import-preview');
        } catch (\Throwable $e) {
            error_log('[ManoboController::importDoc] Error: ' . $e->getMessage());
            flash('error', 'Hindi ma-process ang dokumento: ' . $e->getMessage());
            redirect('/admin/manobo');
        }
    }

    /** GET /admin/manobo/import-preview — render document preview screen. */
    public function importPreview(): void
    {
        $previewData = $_SESSION['dictionary_import_preview'] ?? null;
        if (!$previewData) {
            flash('error', 'Walang data ng import preview. Mangyaring mag-upload muli.');
            redirect('/admin/manobo');
        }

        view('admin/manobo/import-preview', [
            'filename'      => $previewData['filename'],
            'entries'       => $previewData['entries'],
            'unclearCount'  => $previewData['unclear_count'] ?? 0,
            'needsReview'   => $previewData['needs_review'] ?? false,
            'categories'    => $this->dictionary->categories(),
            'partsOfSpeech' => $this->dictionary->partsOfSpeech(),
        ]);
    }

    /** POST /admin/manobo/import-confirm — execute batch import from preview choices. */
    public function importConfirm(): void
    {
        check_csrf();

        $previewData = $_SESSION['dictionary_import_preview'] ?? null;
        $postedEntries = $_POST['entries'] ?? [];

        if (!$previewData || empty($postedEntries) || !is_array($postedEntries)) {
            flash('error', 'Walang napiling entri para i-import.');
            redirect('/admin/manobo');
        }

        $filename = $previewData['filename'] ?? 'DOC-IMPORT-' . date('Y-m-d');
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $importItems = [];
        foreach ($postedEntries as $item) {
            $action = $item['action'] ?? 'import';
            if ($action === 'skip') {
                continue;
            }

            $importItems[] = [
                'manobo'         => trim($item['manobo'] ?? ''),
                'english'        => trim($item['english'] ?? ''),
                'tagalog'        => trim($item['tagalog'] ?? ''),
                'bisaya'         => trim($item['bisaya'] ?? ''),
                'category'       => trim($item['category'] ?? 'general'),
                'part_of_speech' => trim($item['part_of_speech'] ?? 'noun'),
                'notes'          => trim($item['notes'] ?? ''),
                'source_page'    => !empty($item['source_page']) ? (int)$item['source_page'] : null,
                'action'         => $action,
                'existing_id'    => !empty($item['existing_id']) ? (int)$item['existing_id'] : null,
            ];
        }

        if (empty($importItems)) {
            unset($_SESSION['dictionary_import_preview']);
            flash('info', 'Lahat ng entri ay nilaktawan.');
            redirect('/admin/manobo');
        }

        $result = $this->dictionary->importBatch($importItems, $filename, $userId);
        unset($_SESSION['dictionary_import_preview']);

        AuditLog::record(
            $userId,
            'manobo.doc_imported',
            sprintf('Imported %d entry(ies) from %s (Batch ID: %s)', $result['added'] + $result['updated'], $filename, $result['batch_id'])
        );

        flash('success', sprintf(
            'Matagumpay na na-import ang %s! Naidagdag: %d, Na-update: %d, Nilaktawan: %d.',
            htmlspecialchars($filename),
            $result['added'],
            $result['updated'],
            $result['skipped']
        ));

        redirect('/admin/manobo');
    }

    /** POST /admin/manobo/import-undo — safely undo a batch import (admin only). */
    public function undoImport(): void
    {
        check_csrf();

        if (!in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)) {
            flash('error', 'Tanging ang mga admin lamang ang makakapag-undo ng import.');
            redirect('/admin/manobo');
        }

        $batchId = trim($_POST['batch_id'] ?? '');
        if ($batchId === '') {
            flash('error', 'Walang napiling import batch.');
            redirect('/admin/manobo');
        }

        $result = $this->dictionary->undoImport($batchId, (int) ($_SESSION['user_id'] ?? 0));

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'manobo.import_undone',
            sprintf('Undid import batch %s: deleted %d, restored %d', $batchId, $result['deleted'], $result['restored'])
        );

        flash('success', sprintf(
            'Na-undo ang import batch! Tinanggal ang %d bagong entri at ibinalik ang %d lumang entri.',
            $result['deleted'],
            $result['restored']
        ));

        redirect('/admin/manobo');
    }

    /** POST /admin/manobo/regenerate-posts — re-translate all posts with the latest approved dictionary. */
    public function regeneratePosts(): void
    {
        check_csrf();

        try {
            $stats = \App\Services\TranslationService::regenerateAllManoboTranslations();
            $total = $stats['announcements'] + $stats['events'] + $stats['ordinances'];

            AuditLog::record(
                (int) $_SESSION['user_id'],
                'manobo.posts_regenerated',
                sprintf('Regenerated MN translations for %d posts (Announcements: %d, Events: %d, Ordinances: %d)',
                    $total, $stats['announcements'], $stats['events'], $stats['ordinances'])
            );

            flash('success', sprintf(
                'Na-regenerate ang MN bersyon para sa %d post! (Announcements: %d, Events: %d, Ordinances: %d)',
                $total, $stats['announcements'], $stats['events'], $stats['ordinances']
            ));
        } catch (\Throwable $e) {
            error_log('[ManoboController::regeneratePosts] Error: ' . $e->getMessage());
            flash('error', 'Nagkaroon ng error sa pag-regenerate ng mga post: ' . $e->getMessage());
        }

        redirect('/admin/manobo');
    }


    /**
     * GET /admin/manobo/trash — the Trash lives inline on the main admin
     * screen (adminIndex() already passes it); this just anchors there so a
     * "View trash" link elsewhere has somewhere to point.
     */
    public function trashIndex(): void
    {
        redirect('/admin/manobo#trash');
    }

    /** GET /admin/bisaya/trash — see trashIndex() above. */
    public function bisayaTrashIndex(): void
    {
        redirect('/admin/bisaya#trash');
    }

    /** POST /admin/manobo/trash/restore — admin-only, undoes a delete. */
    public function restore(): void
    {
        check_csrf();
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $this->dictionary->restore($id);
            ManoboAutoTranslator::flushCache();
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/manobo');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'manobo.entry_restored', 'Restored Manobo word id ' . $id);
        flash('success', 'Naibalik ang salita.');
        redirect('/admin/manobo');
    }

    /** POST /admin/manobo/trash/delete — admin-only, cannot be undone. */
    public function forceDelete(): void
    {
        check_csrf();
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $this->dictionary->forceDelete($id);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/manobo');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'manobo.entry_purged', 'Permanently deleted Manobo word id ' . $id);
        flash('success', 'Tinanggal nang tuluyan.');
        redirect('/admin/manobo');
    }

    /** POST /admin/manobo/import — optional CSV upload (manobo,tagalog,english,category). */
    public function importCsv(): void
    {
        check_csrf();

        $file = $_FILES['csv_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Pumili ng CSV file na i-upload.');
            redirect('/admin/manobo');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'text/x-csv'], true)) {
            flash('error', 'Ang file ay dapat CSV.');
            redirect('/admin/manobo');
        }

        $result = $this->dictionary->importCsv(
            $file['tmp_name'],
            'CSV-IMPORT-' . date('Y-m-d'),
            (int) ($_SESSION['user_id'] ?? 0) ?: null
        );
        ManoboAutoTranslator::flushCache();

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'manobo.csv_imported',
            \sprintf('Imported %d word(s), skipped %d duplicate(s)', $result['added'], $result['skipped'])
        );

        flash('success', \sprintf(
            'Naidagdag: %d. Nilaktawan (mayroon na): %d.%s',
            $result['added'],
            $result['skipped'],
            $result['errors'] !== [] ? ' Mga error: ' . implode('; ', $result['errors']) : ''
        ));
        redirect('/admin/manobo');
    }

    /**
     * POST /api/manobo/translate-block — the resident-facing "MN" button.
     *
     * Segments arbitrary text into phrase/word matches (longest phrase
     * first), falling back to Bisaya, tagging each piece with where it came
     * from. See App\Services\ManoboAutoTranslator for the matching rules.
     * Dictionary-only, no AI call — this is what makes the MN button work
     * with zero API credits, same reasoning as draft() above.
     */
    public function translateBlock(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $text = trim((string) ($_POST['text'] ?? ''));
        if ($text === '') {
            echo json_encode(['success' => false, 'error' => t('admin_manobo.draft_empty_source')]);
            return;
        }

        // A whole announcement body is plausible input, not just a UI label —
        // capped generously, still all local string work.
        $text = mb_substr($text, 0, 20000);

        try {
            $hybrid = (new \App\Services\ManoboHybridTranslator())->translate($text);
            $segments = [];
            foreach ($hybrid['provenance'] as $p) {
                $segments[] = [
                    'text'    => $p['text'],
                    'display' => $p['translated'],
                    'source'  => $p['source'],
                ];
            }
            $result = [
                'success'        => true,
                'segments'       => $segments,
                'translation'    => $hybrid['translation'],
                'matched_manobo' => $hybrid['manoboMatches'],
                'matched_bisaya' => $hybrid['bisayaFallbacks'],
                'unmatched'      => 0,
            ];
        } catch (\Throwable $e) {
            error_log('[ManoboController::translateBlock] ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => t('admin_manobo.draft_none')]);
            return;
        }

        echo json_encode($result);
    }

    /**
     * POST /api/translate/manobo
     *
     * Requirement 27: Reusable hybrid translation API.
     * Request:  { "text": "Good morning. There will be a meeting tomorrow." }
     * Response: { "success": true, "language": "mn",
     *             "translation": "...", "manoboMatches": 2,
     *             "bisayaFallbacks": 1 }
     */
    public function apiTranslateManobo(): void
    {
        header('Content-Type: application/json');

        // IP-based rate limiting: max 60 requests per minute
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $rateKey = 'rate_trans_' . md5($ip);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $now = time();
            $hits = $_SESSION[$rateKey] ?? [];
            $hits = array_filter($hits, fn($t) => $t > $now - 60);
            if (count($hits) >= 60) {
                http_response_code(429);
                echo json_encode(['success' => false, 'error' => 'Too many translation requests. Please wait a moment.']);
                return;
            }
            $hits[] = $now;
            $_SESSION[$rateKey] = $hits;
        }

        $input = json_decode(file_get_contents('php://input') ?: '', true);
        $text = trim((string) ($input['text'] ?? $_POST['text'] ?? ''));

        if ($text === '') {
            echo json_encode([
                'success' => false,
                'error'   => 'No text was provided for translation.',
            ]);
            return;
        }

        $text = mb_substr($text, 0, 20000);

        try {
            $translator = new \App\Services\ManoboHybridTranslator();
            $result = $translator->translate($text);

            $response = [
                'success'         => true,
                'language'        => 'mn',
                'translation'     => $result['translation'],
                'manoboMatches'   => $result['manoboMatches'],
                'bisayaFallbacks' => $result['bisayaFallbacks'],
            ];

            // For admin / staff users, include provenance metadata
            if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff', 'superadmin'], true)) {
                $response['provenance'] = $result['provenance'];
            }

            echo json_encode($response);
        } catch (\Throwable $e) {
            error_log('[ManoboController::apiTranslateManobo] ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error'   => 'Translation failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/manobo/draft
     *
     * Word-by-word Manobo draft for the admin translation fields, built from
     * the barangay's own dictionary.
     *
     * A drafting aid, not a translation: it gives whoever is writing the
     * Manobo version a head start on the words the community has already
     * collected, so they correct rather than type from scratch — and it makes
     * the value of adding dictionary entries immediately visible.
     *
     * Uses ManoboDictionary only. No AI call, so it costs nothing and works
     * with no API credits. Writes nothing: the draft lands in a form field
     * that a human still has to review and save.
     *
     * Deliberately more permissive than the resident-facing gloss in
     * AIController: there, one matched word in a long text is noise; here,
     * every matched word is typing saved, and an editor sees it before
     * anything is published.
     */
    public function draft(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $text = trim((string) ($_POST['text'] ?? ''));
        if ($text === '') {
            echo json_encode(['success' => false, 'error' => t('admin_manobo.draft_empty_source')]);
            return;
        }

        // Long bodies are fine — this is all local string work.
        $text = mb_substr(strip_tags($text), 0, 5000);

        try {
            $result = $this->dictionary->translate($text, 'manobo');
        } catch (\Throwable $e) {
            error_log('[ManoboController::draft] ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => t('admin_manobo.draft_none')]);
            return;
        }

        $tokens  = $result['tokens'] ?? [];
        $total   = \count($tokens);
        $matched = \count(array_filter($tokens, static fn (array $t): bool => !empty($t['found'])));

        if (($result['found'] ?? false) !== true || $matched === 0) {
            echo json_encode([
                'success' => false,
                'error'   => t('admin_manobo.draft_none'),
                'matched' => 0,
                'total'   => $total,
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'text'    => (string) ($result['text'] ?? ''),
            'matched' => $matched,
            'total'   => $total,
            'missing' => array_values(array_unique(array_slice($result['missing'] ?? [], 0, 25))),
        ]);
    }

    /**
     * POST /api/english/draft
     *
     * Word-by-word English draft of a Filipino passage, built from the
     * tagalog/english columns the community dictionaries already carry.
     *
     * The English counterpart to draft() above, and it exists for the same
     * reason: the AI translator needs Anthropic credits, and without them a
     * staff member otherwise has to type every English translation from
     * scratch. This gives them a first pass to correct.
     *
     * Writes nothing and calls no API. The draft lands in a form field that a
     * human reviews before anything is published — which is exactly why it is
     * allowed to be rough, and why it must never be shown to a resident as a
     * finished translation.
     */
    public function draftEnglish(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $text = trim((string) ($_POST['text'] ?? ''));
        if ($text === '') {
            echo json_encode(['success' => false, 'error' => t('admin_manobo.draft_empty_source')]);
            return;
        }

        $text   = mb_substr(strip_tags($text), 0, 5000);
        $result = english_gloss_phrase($text);

        if ($result['matched'] === 0) {
            echo json_encode([
                'success' => false,
                'error'   => t('admin_manobo.draft_none'),
                'matched' => 0,
                'total'   => $result['total'],
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'text'    => $result['text'],
            'matched' => $result['matched'],
            'total'   => $result['total'],
            'missing' => array_slice($result['missing'], 0, 25),
        ]);
    }

    // ── Actions ───────────────────────────────────────────────────────────

    /** POST /admin/manobo — add a new word. Admin or staff. */
    public function store(): void
    {
        check_csrf();
        $userId = (int) ($_SESSION['user_id'] ?? 0) ?: null;

        try {
            $this->dictionary->addEntry($this->input(), $userId);
            ManoboAutoTranslator::flushCache();
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/manobo');
        } catch (\Throwable $e) {
            error_log('[ManoboController::store] ' . $e->getMessage());
            flash('error', 'Could not save the word. Check the database connection.');
            redirect('/admin/manobo');
        }

        $headword = trim($_POST['manobo'] ?? '');

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'manobo.entry_added',
            'Added Manobo word: ' . $headword
        );

        flash('success', 'Added "' . $headword . '" to the dictionary.');
        redirect('/admin/manobo');
    }

    /** POST /admin/manobo/update — correct an existing word. Admin or staff. */
    public function update(): void
    {
        check_csrf();
        $original = trim($_POST['original_manobo'] ?? '');
        $userId   = (int) ($_SESSION['user_id'] ?? 0) ?: null;

        try {
            $this->dictionary->updateEntry($original, $this->input(), $userId);
            ManoboAutoTranslator::flushCache();
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/manobo');
        } catch (\Throwable $e) {
            error_log('[ManoboController::update] ' . $e->getMessage());
            flash('error', 'Could not update the word. Check the database connection.');
            redirect('/admin/manobo');
        }

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'manobo.entry_updated',
            'Updated Manobo word: ' . $original
        );

        flash('success', 'Updated "' . $original . '".');
        redirect('/admin/manobo');
    }

    /**
     * POST /admin/manobo/delete — move a word to Trash. Admin or staff, per
     * the brief ("add, edit, and delete — with Trash"); permanently removing
     * it (forceDelete(), above) stays admin-only.
     */
    public function destroy(): void
    {
        check_csrf();

        $headword = trim($_POST['manobo'] ?? '');

        try {
            $this->dictionary->deleteEntry($headword);
            ManoboAutoTranslator::flushCache();
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/manobo');
        } catch (\Throwable $e) {
            error_log('[ManoboController::destroy] ' . $e->getMessage());
            flash('error', 'Could not delete the word. Check the database connection.');
            redirect('/admin/manobo');
        }

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'manobo.entry_deleted',
            'Deleted Manobo word: ' . $headword
        );

        flash('success', 'Deleted "' . $headword . '".');
        redirect('/admin/manobo');
    }

    /** GET /admin/manobo/export — download the dataset as CSV. */
    public function export(): void
    {
        $filename = 'manobo_dictionary_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads the accents correctly
        fputcsv($out, ManoboDictionary::FIELDS);
        foreach ($this->dictionary->all() as $entry) {
            $row = [];
            foreach (ManoboDictionary::FIELDS as $field) {
                $row[] = $entry[$field] ?? '';
            }
            fputcsv($out, $row);
        }
        fclose($out);

        AuditLog::record((int) $_SESSION['user_id'], 'manobo.exported', 'Exported the Manobo dictionary');
        exit;
    }

    // ── Bisaya (mirrors the Manobo actions above) ───────────────────────────

    /** GET /admin/bisaya */
    public function bisayaAdminIndex(): void
    {
        $search   = trim($_GET['q']        ?? '');
        $category = trim($_GET['category'] ?? '');
        $tryTerm  = trim($_GET['try']      ?? '');
        $tryTo    = trim($_GET['to']       ?? 'english');

        $tryResult = null;
        if ($tryTerm !== '') {
            try {
                $tryResult = $this->bisaya->translate($tryTerm, $tryTo);
            } catch (\Throwable $e) {
                $tryResult = null;
            }
        }

        view('admin/bisaya/index', [
            'entries'           => $this->bisaya->search($search, $category),
            'totalEntries'      => $this->bisaya->count(),
            'categories'        => $this->bisaya->categories(),
            'partsOfSpeech'     => $this->bisaya->partsOfSpeech(),
            'search'            => $search,
            'filterCategory'    => $category,
            'tryTerm'           => $tryTerm,
            'tryTo'             => $tryTo,
            'tryResult'         => $tryResult,
            'needsVerification' => $this->bisaya->needsVerification(),
            'trash'             => $this->bisaya->trash(),
            'canRestore'        => in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true),
            'canDelete'         => in_array($_SESSION['role'] ?? '', ['admin', 'staff', 'superadmin'], true),
        ]);
    }

    /** POST /admin/bisaya */
    public function bisayaStore(): void
    {
        check_csrf();
        $userId = (int) ($_SESSION['user_id'] ?? 0) ?: null;

        try {
            $this->bisaya->addEntry($this->bisayaInput(), $userId);
            ManoboAutoTranslator::flushCache();
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/bisaya');
        } catch (\Throwable $e) {
            error_log('[ManoboController::bisayaStore] ' . $e->getMessage());
            flash('error', 'Could not save the word. Check the database connection.');
            redirect('/admin/bisaya');
        }

        $headword = trim($_POST['bisaya'] ?? '');
        AuditLog::record((int) $_SESSION['user_id'], 'bisaya.entry_added', 'Added Bisaya word: ' . $headword);
        flash('success', 'Added "' . $headword . '" to the dictionary.');
        redirect('/admin/bisaya');
    }

    /** POST /admin/bisaya/update */
    public function bisayaUpdate(): void
    {
        check_csrf();
        $original = trim($_POST['original_bisaya'] ?? '');
        $userId   = (int) ($_SESSION['user_id'] ?? 0) ?: null;

        try {
            $this->bisaya->updateEntry($original, $this->bisayaInput(), $userId);
            ManoboAutoTranslator::flushCache();
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/bisaya');
        } catch (\Throwable $e) {
            error_log('[ManoboController::bisayaUpdate] ' . $e->getMessage());
            flash('error', 'Could not update the word. Check the database connection.');
            redirect('/admin/bisaya');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'bisaya.entry_updated', 'Updated Bisaya word: ' . $original);
        flash('success', 'Updated "' . $original . '".');
        redirect('/admin/bisaya');
    }

    /** POST /admin/bisaya/delete — moves to Trash. */
    public function bisayaDestroy(): void
    {
        check_csrf();
        $headword = trim($_POST['bisaya'] ?? '');

        try {
            $this->bisaya->deleteEntry($headword);
            ManoboAutoTranslator::flushCache();
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/bisaya');
        } catch (\Throwable $e) {
            error_log('[ManoboController::bisayaDestroy] ' . $e->getMessage());
            flash('error', 'Could not delete the word. Check the database connection.');
            redirect('/admin/bisaya');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'bisaya.entry_deleted', 'Deleted Bisaya word: ' . $headword);
        flash('success', 'Deleted "' . $headword . '".');
        redirect('/admin/bisaya');
    }

    /** POST /admin/bisaya/trash/restore — admin-only. */
    public function bisayaRestore(): void
    {
        check_csrf();
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $this->bisaya->restore($id);
            ManoboAutoTranslator::flushCache();
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/bisaya');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'bisaya.entry_restored', 'Restored Bisaya word id ' . $id);
        flash('success', 'Naibalik ang salita.');
        redirect('/admin/bisaya');
    }

    /** POST /admin/bisaya/trash/delete — admin-only, permanent. */
    public function bisayaForceDelete(): void
    {
        check_csrf();
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $this->bisaya->forceDelete($id);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/bisaya');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'bisaya.entry_purged', 'Permanently deleted Bisaya word id ' . $id);
        flash('success', 'Tinanggal nang tuluyan.');
        redirect('/admin/bisaya');
    }

    /** POST /admin/bisaya/import */
    public function bisayaImportCsv(): void
    {
        check_csrf();

        $file = $_FILES['csv_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Pumili ng CSV file na i-upload.');
            redirect('/admin/bisaya');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'text/x-csv'], true)) {
            flash('error', 'Ang file ay dapat CSV.');
            redirect('/admin/bisaya');
        }

        $result = $this->bisaya->importCsv(
            $file['tmp_name'],
            'CSV-IMPORT-' . date('Y-m-d'),
            (int) ($_SESSION['user_id'] ?? 0) ?: null
        );
        ManoboAutoTranslator::flushCache();

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'bisaya.csv_imported',
            \sprintf('Imported %d word(s), skipped %d duplicate(s)', $result['added'], $result['skipped'])
        );

        flash('success', \sprintf(
            'Naidagdag: %d. Nilaktawan (mayroon na): %d.%s',
            $result['added'],
            $result['skipped'],
            $result['errors'] !== [] ? ' Mga error: ' . implode('; ', $result['errors']) : ''
        ));
        redirect('/admin/bisaya');
    }

    /** GET /admin/bisaya/export */
    public function bisayaExport(): void
    {
        $filename = 'bisaya_dictionary_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, BisayaDictionary::FIELDS);
        foreach ($this->bisaya->all() as $entry) {
            $row = [];
            foreach (BisayaDictionary::FIELDS as $field) {
                $row[] = $entry[$field] ?? '';
            }
            fputcsv($out, $row);
        }
        fclose($out);

        AuditLog::record((int) $_SESSION['user_id'], 'bisaya.exported', 'Exported the Bisaya dictionary');
        exit;
    }

    /** @return array<string,string> */
    private function bisayaInput(): array
    {
        $data = [];
        foreach (BisayaDictionary::FIELDS as $field) {
            $data[$field] = (string) ($_POST[$field] ?? '');
        }

        return $data;
    }

    // ── Internal helpers ──────────────────────────────────────────────────

    /**
     * The interface labels that still have no Manobo word.
     *
     * This is the worklist to bring to a Manobo speaker: every one of these
     * that gets filled in makes that much more of the UI render in Manobo,
     * with no code change. Only short, literal labels are listed — strings
     * with :placeholders or full sentences do not belong in a word dictionary.
     *
     * @return list<string>
     */
    private function neededUiWords(): array
    {
        $strings = [];
        $flatten = static function (array $node) use (&$flatten, &$strings): void {
            foreach ($node as $value) {
                if (\is_array($value)) {
                    $flatten($value);
                } elseif (\is_string($value)) {
                    $strings[] = $value;
                }
            }
        };
        $flatten(require \dirname(__DIR__, 2) . '/lang/en.php');

        // The everyday words worth collecting first — a speaker's time is
        // better spent on "Home" and "Search" than on "AI Simplifications".
        $priority = [
            'Yes', 'No', 'Home', 'Search', 'Save', 'Cancel', 'Delete', 'Edit', 'Back',
            'Name', 'Email', 'Address', 'Phone', 'Date', 'Time', 'Today',
            'Announcements', 'Events', 'Ordinances', 'Notifications', 'Feedback',
            'Residents', 'Profile', 'Dashboard', 'Reports', 'Settings',
            'Sign In', 'Sign Out', 'Submit', 'Send', 'Close', 'Open', 'Help',
            'Status', 'Category', 'Title', 'Message', 'Password', 'Verified', 'Pending',
        ];

        $needed = [];
        foreach (array_unique($strings) as $label) {
            if (str_contains($label, ':') || str_contains($label, '<') || str_contains($label, '(')) {
                continue;                                   // placeholder, markup or aside
            }
            if (str_starts_with($label, 'AI ') || str_contains($label, 'BarangGabay')) {
                continue;                                   // product jargon, not vocabulary
            }
            if (mb_strlen($label) > 18 || str_word_count($label) > 2) {
                continue;                                   // a sentence, not a word
            }
            if (manobo_word($label) !== null) {
                continue;                                   // already covered
            }
            $needed[] = $label;
        }

        sort($needed, SORT_NATURAL | SORT_FLAG_CASE);

        // Priority words first, in the order listed above; everything else after.
        $ranked = array_values(array_intersect($priority, $needed));
        $rest   = array_values(array_diff($needed, $ranked));

        return array_slice(array_merge($ranked, $rest), 0, 60);
    }

    /**
     * Pull the dictionary fields out of the POST body.
     *
     * @return array<string,string>
     */
    private function input(): array
    {
        $data = [];
        foreach (ManoboDictionary::FIELDS as $field) {
            $data[$field] = (string) ($_POST[$field] ?? '');
        }

        return $data;
    }
}
