<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\EvacuationCenter;
use App\Models\SafetyCheckin;

/**
 * The two things a coastal barangay needs when the wind comes up: where do I
 * go, and who have we not heard from.
 *
 * Bayogo sits on the Pacific side of Mindanao. Both questions are answered per
 * purok, because both answers differ per purok and neither is useful as a
 * barangay-wide list read on a phone during a storm.
 */
class EmergencyController
{
    // ── Resident ─────────────────────────────────────────────────────────

    /**
     * GET /evacuation — where THIS resident goes.
     *
     * Their own purok's centre first, then any that serve the whole barangay.
     * A resident whose purok has no centre of its own still gets an answer;
     * an empty page during a storm warning is the one outcome this must never
     * produce.
     */
    public function centers(): void
    {
        $purok = $this->currentPurok();

        $centers = EvacuationCenter::forPurok($purok);
        $mapsKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';

        view('resident/evacuation', compact('centers', 'purok', 'mapsKey'));
    }

    /**
     * POST /safety-checkin/{id} — "Ligtas ako", or "kailangan ko ng tulong".
     *
     * One tap. Anything longer than that is too much to ask of someone who has
     * just come through a typhoon on a phone with a dying battery.
     */
    public function checkIn(array $params): void
    {
        check_csrf();

        $advisoryId = (int) ($params['id'] ?? 0);
        $userId     = (int) ($_SESSION['user_id'] ?? 0);
        $status     = ($_POST['status'] ?? 'safe') === 'needs_help' ? 'needs_help' : 'safe';
        $note       = trim($_POST['note'] ?? '');

        $advisory = Announcement::find($advisoryId);

        // Only advisories that asked for a check-in accept one, so a stray
        // POST cannot attach "safe" answers to an ordinary notice.
        if ($advisory === null || (int) ($advisory['asks_safety_checkin'] ?? 0) !== 1) {
            flash('error', t('safety.err_not_open'));
            redirect('/announcements');
        }

        SafetyCheckin::record($advisoryId, $userId, $status, $note);

        flash('success', $status === 'safe' ? t('safety.thanks_safe') : t('safety.thanks_help'));
        redirect('/announcements/' . (string) $advisory['slug']);
    }

    // ── Admin ────────────────────────────────────────────────────────────

    /**
     * GET /admin/safety — who has not answered.
     *
     * The roll-up leads with needs_help and then silence, because those are
     * the two columns anyone acts on. The count of the safe is reassurance;
     * the count of the silent is a work list.
     */
    public function adminRollUp(): void
    {
        $advisories = SafetyCheckin::activeAdvisories();
        $advisoryId = (int) ($_GET['advisory'] ?? 0);

        // Default to the most recent advisory asking for a check-in — during
        // an emergency that is almost always the one being asked about.
        if ($advisoryId === 0 && $advisories !== []) {
            $advisoryId = (int) $advisories[0]['id'];
        }

        $rollUp    = $advisoryId > 0 ? SafetyCheckin::rollUp($advisoryId) : [];
        $attention = $advisoryId > 0 ? SafetyCheckin::needingAttention($advisoryId) : [];

        $pageTitle    = t('admin_safety.title');
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

        view('admin/safety/index', compact(
            'advisories', 'advisoryId', 'rollUp', 'attention', 'pageTitle', 'pendingCount'
        ));
    }

    /** GET /admin/evacuation — manage the centres. */
    public function adminCenters(): void
    {
        $centers      = EvacuationCenter::allAdmin();
        $puroks       = barangay_subdivisions();
        $pageTitle    = t('admin_evacuation.title');
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

        view('admin/evacuation/index', compact('centers', 'puroks', 'pageTitle', 'pendingCount'));
    }

    /** POST /admin/evacuation */
    public function storeCenter(): void
    {
        check_csrf();

        if (trim($_POST['name'] ?? '') === '') {
            flash('error', t('admin_evacuation.err_name'));
            redirect('/admin/evacuation');
        }

        $id = EvacuationCenter::create($this->centerPayload($_POST));

        AuditLog::record((int) $_SESSION['user_id'], 'evacuation.create', 'Added centre #' . $id);
        flash('success', t('admin_evacuation.added'));
        redirect('/admin/evacuation');
    }

    /** POST /admin/evacuation/{id} */
    public function updateCenter(array $params): void
    {
        check_csrf();

        $id = (int) ($params['id'] ?? 0);
        if (EvacuationCenter::find($id) === null) {
            flash('error', t('flash.not_found'));
            redirect('/admin/evacuation');
        }

        if (trim($_POST['name'] ?? '') === '') {
            flash('error', t('admin_evacuation.err_name'));
            redirect('/admin/evacuation');
        }

        EvacuationCenter::update($id, $this->centerPayload($_POST));

        AuditLog::record((int) $_SESSION['user_id'], 'evacuation.update', 'Updated centre #' . $id);
        flash('success', t('admin_evacuation.updated'));
        redirect('/admin/evacuation');
    }

    /** POST /admin/evacuation/{id}/delete */
    public function deleteCenter(array $params): void
    {
        check_csrf();

        $id = (int) ($params['id'] ?? 0);
        EvacuationCenter::delete($id);

        AuditLog::record((int) $_SESSION['user_id'], 'evacuation.delete', 'Removed centre #' . $id);
        flash('success', t('admin_evacuation.deleted'));
        redirect('/admin/evacuation');
    }

    // ── Internals ────────────────────────────────────────────────────────

    /** The signed-in resident's purok, or null if they have not recorded one. */
    private function currentPurok(): ?string
    {
        $stmt = db()->prepare('SELECT zone FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
        $zone = trim((string) ($stmt->fetchColumn() ?: ''));

        return $zone !== '' ? $zone : null;
    }

    /**
     * Normalise the centre form.
     *
     * The purok is checked against the barangay's own list, so a typo cannot
     * create a centre that serves a purok nobody lives in — and therefore
     * appears to no one.
     *
     * @param  array<string,mixed> $post
     * @return array<string,mixed>
     */
    private function centerPayload(array $post): array
    {
        $purok = trim((string) ($post['purok'] ?? ''));
        if ($purok !== '' && !\in_array($purok, barangay_subdivisions(), true)) {
            $purok = '';   // falls back to "serves the whole barangay"
        }

        return [
            'name'           => $post['name']           ?? '',
            'purok'          => $purok,
            'address'        => $post['address']        ?? '',
            'latitude'       => $post['latitude']       ?? '',
            'longitude'      => $post['longitude']      ?? '',
            'capacity'       => $post['capacity']       ?? '',
            'contact_person' => $post['contact_person'] ?? '',
            'contact_phone'  => $post['contact_phone']  ?? '',
            'is_active'      => !empty($post['is_active']),
        ];
    }
}
