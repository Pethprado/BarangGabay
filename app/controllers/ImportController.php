<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Services\FileService;
use App\Services\LanguageGuess;
use App\Services\LinkImporter;
use App\Services\SocialText;
use App\Services\SourceLink;

/**
 * Pulls content in from a pasted link, or cleans up a pasted caption.
 *
 * Three rules apply to everything here, and they are the reason this is a
 * separate controller rather than a helper bolted onto the post forms:
 *
 *   Nothing is saved. Every endpoint returns a draft for a staff member to
 *   read, edit and then submit through the ordinary create form. An official
 *   barangay channel must not republish whatever a link happened to contain,
 *   and the person pressing Publish has to have seen the words first.
 *
 *   Everything is attributed. The address actually reached comes back with the
 *   draft so it can be stored alongside the post. Text from the barangay's own
 *   Page is theirs to reuse; a news outlet's article is not, and for those the
 *   embed-with-a-link is the honest option.
 *
 *   Every fetch is logged and rate-limited. These endpoints make outbound
 *   requests to an address the caller chose, so they are staff-only, counted,
 *   and written to the audit trail with where they went.
 */
class ImportController
{
    /** Outbound fetches allowed per staff member per hour. */
    private const FETCHES_PER_HOUR = 30;

    /** Cover images re-hosted from a link. Matches the upload form's cap. */
    private const MAX_IMAGE_BYTES = 5_242_880;

    /** Ordinance PDFs pulled from Drive. Matches the upload form's cap. */
    private const MAX_PDF_BYTES = 10_485_760;

    /**
     * POST /api/admin/import/link
     *
     * Fetch a URL and return an editable draft: title, body, cover image and
     * the platform we recognised. Image is re-hosted locally on the way past.
     */
    public function fromLink(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $url = trim((string) ($_POST['url'] ?? ''));
        if ($url === '') {
            $this->say(false, t('import.err_no_url'));
        }

        if (!$this->withinRateLimit()) {
            http_response_code(429);
            $this->say(false, t('import.err_rate_limited'));
        }

        $source   = SourceLink::detect($url);
        $importer = new LinkImporter();
        $page     = $importer->fetchPage($url);

        $this->log('import.link', $url, $page['ok'] ? 'ok' : ('refused: ' . ($page['code'] ?? '?')));

        if (!$page['ok']) {
            /*
             * A refusal is only useful if it says which refusal. "Could not
             * import" sends a staff member to retype the whole post without
             * knowing that Facebook will never work this way and that the
             * embed tab beside them will.
             */
            echo json_encode([
                'success'  => false,
                'code'     => $page['code'],
                'error'    => t('import.err_' . $page['code'], ['detail' => (string) $page['error']]),
                'platform' => $source['platform'],
                // Facebook and friends get the specific advice, not the generic.
                'suggest'  => $page['code'] === 'login_wall' ? 'embed_or_paste' : null,
            ]);
            return;
        }

        $meta  = $page['meta'];
        $cover = null;
        $note  = null;

        if (!empty($meta['image'])) {
            try {
                $image = $importer->fetchFile(
                    $meta['image'],
                    ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                    self::MAX_IMAGE_BYTES
                );

                if ($image['ok']) {
                    $cover = (new FileService())->storeFetched(
                        $image['bytes'],
                        'announcements',
                        ['image/jpeg', 'image/png'],
                        self::MAX_IMAGE_BYTES
                    );
                }
            } catch (\Throwable $e) {
                // A cover picture is the least important part of an import;
                // losing it must not lose the text with it.
                error_log('[ImportController] cover image: ' . $e->getMessage());
                $note = t('import.note_no_image');
            }
        }

        echo json_encode([
            'success'  => true,
            'platform' => $source['platform'],
            'embed'    => $source['embed'],
            'source'   => $page['url'],
            'title'    => $meta['title'] ?? '',
            // Presented as paragraphs so it drops straight into Quill. The
            // controller that saves it still runs it through HTMLPurifier.
            'body'     => SocialText::toHtml($meta['description'] ?? ''),
            'text'     => $meta['description'] ?? '',
            // What language the imported text is in, so the form's source
            // language follows the post instead of the barangay's usual one.
            // Getting this wrong is not cosmetic: a post filed as Filipino is
            // never translated INTO Filipino, so residents reading in FIL are
            // shown the English original under a Filipino badge. null when the
            // text does not say clearly enough — the form then keeps its own
            // setting rather than acting on a coin flip.
            'source_lang' => LanguageGuess::detectOrNull(
                ($meta['title'] ?? '') . "\n" . ($meta['description'] ?? '')
            ),
            'cover'    => $cover,
            'site'     => $meta['site'] ?? '',
            'published'=> $meta['published'] ?? '',
            'note'     => $note,
        ]);
    }

    /**
     * POST /api/admin/import/paste
     *
     * Tier 3, and the one that always works: clean up a caption copied out of
     * Facebook. No network call, so no rate limit and nothing to refuse.
     */
    public function fromPaste(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $raw = (string) ($_POST['text'] ?? '');
        if (trim($raw) === '') {
            $this->say(false, t('import.err_no_text'));
        }

        $clean = SocialText::clean(mb_substr($raw, 0, 20000));

        echo json_encode([
            'success' => true,
            'text'    => $clean['text'],
            'body'    => $clean['html'],
            'tags'    => $clean['tags'],
            'links'   => $clean['links'],
            // As in fromLink(): a pasted English caption must not be filed as
            // Filipino, or it never gets a Filipino translation at all.
            'source_lang' => LanguageGuess::detectOrNull($clean['text']),
            // Named so the panel can say what it tidied rather than silently
            // rewriting what somebody pasted.
            'removed' => array_map(
                static fn (string $what): string => t('import.removed_' . $what),
                $clean['removed']
            ),
        ]);
    }

    /**
     * POST /api/admin/import/file
     *
     * Fetch a document from a link — a Drive PDF for an ordinance — and store
     * it through the same validated upload path a browser upload uses.
     */
    public function fromFile(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $url = trim((string) ($_POST['url'] ?? ''));
        if ($url === '') {
            $this->say(false, t('import.err_no_url'));
        }

        if (!$this->withinRateLimit()) {
            http_response_code(429);
            $this->say(false, t('import.err_rate_limited'));
        }

        // A Drive "view" link is a web page; the download endpoint is what
        // actually returns the file.
        $target = SourceLink::driveDownloadUrl($url) ?? $url;

        $file = (new LinkImporter())->fetchFile($target, ['application/pdf'], self::MAX_PDF_BYTES);
        $this->log('import.file', $url, $file['ok'] ? 'ok' : ('refused: ' . ($file['code'] ?? '?')));

        if (!$file['ok']) {
            /*
             * Drive has three ways of saying "you cannot have this file", and
             * none of them says it plainly: a sign-in PAGE instead of the PDF
             * (wrong content type), a 404 for a file that exists but is not
             * shared, or a 403. All three mean the same thing to a staff
             * member, so they get the same sentence — one that names the fix.
             */
            $notPublic = str_contains($target, 'drive.google.com')
                && (
                    $file['code'] === 'wrong_type'
                    || ($file['code'] === 'http_error' && in_array($file['error'], ['404', '403'], true))
                );

            $code = $notPublic ? 'drive_not_public' : (string) $file['code'];

            echo json_encode([
                'success' => false,
                'code'    => $code,
                'error'   => t('import.err_' . $code, ['detail' => (string) $file['error']]),
            ]);
            return;
        }

        try {
            $path = (new FileService())->storeFetched(
                $file['bytes'],
                'ordinances',
                ['application/pdf'],
                self::MAX_PDF_BYTES
            );
        } catch (\Throwable $e) {
            error_log('[ImportController::fromFile] ' . $e->getMessage());
            $this->say(false, $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'path'    => $path,
            'source'  => $url,
            'size_kb' => (int) round(strlen($file['bytes']) / 1024),
        ]);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Outbound fetches per staff member per hour.
     *
     * Counted from the audit log rather than a new table: every fetch is
     * already written there for attribution, so the count is a by-product of a
     * record that has to exist anyway.
     */
    private function withinRateLimit(): bool
    {
        try {
            $stmt = db()->prepare(
                "SELECT COUNT(*) FROM audit_logs
                  WHERE user_id = ?
                    AND action IN ('import.link', 'import.file')
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);

            return (int) $stmt->fetchColumn() < self::FETCHES_PER_HOUR;
        } catch (\Throwable $e) {
            // Never block staff from working because the counter is unavailable.
            error_log('[ImportController] rate limit check: ' . $e->getMessage());
            return true;
        }
    }

    /** Record what was fetched and from where — the attribution trail. */
    private function log(string $action, string $url, string $outcome): void
    {
        AuditLog::record(
            (int) ($_SESSION['user_id'] ?? 0),
            $action,
            mb_substr($url, 0, 400) . ' — ' . $outcome
        );
    }

    /** Emit a terminal JSON response. */
    private function say(bool $ok, string $message): never
    {
        echo json_encode(['success' => $ok, 'error' => $message]);
        exit;
    }
}
