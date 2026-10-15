<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Purok;
use App\Models\User;

/**
 * Puroks: how many residents live in each purok and in the whole barangay,
 * and adding residents to a purok (existing accounts, or registering a new
 * resident in person). Admin and staff.
 */
class PurokController
{
    /** The ?purok= value for residents who have no purok yet. */
    public const NONE = 'none';

    /**
     * GET /admin/puroks
     * Totals, one row per purok, and the members of the selected purok.
     */
    public function index(): void
    {
        $selected = trim((string) ($_GET['purok'] ?? ''));
        $search   = trim((string) ($_GET['q'] ?? ''));
        $find     = trim((string) ($_GET['find'] ?? ''));

        $totals = Purok::totals();
        $puroks = Purok::summary();

        $members    = [];
        $candidates = [];
        if ($selected === self::NONE) {
            $members = Purok::members('', $search);
        } elseif ($selected !== '' && Purok::isKnown($selected)) {
            $members    = Purok::members($selected, $search);
            $candidates = Purok::candidates($selected, $find);
        } else {
            $selected = '';
        }

        // Shown once, right after registering a resident with an email.
        $newLogin = $_SESSION['purok_new_login'] ?? null;
        unset($_SESSION['purok_new_login']);

        $pendingCount = User::countByStatus('pending');
        $pageTitle    = 'Puroks & Residents';

        view('admin/puroks/index', compact(
            'totals', 'puroks', 'selected', 'search', 'find',
            'members', 'candidates', 'newLogin', 'pendingCount', 'pageTitle'
        ));
    }

    /**
     * POST /admin/puroks/assign
     * Add the ticked residents to a purok (moving them from any other purok).
     */
    public function assign(): void
    {
        check_csrf();

        $purok = trim((string) ($_POST['purok'] ?? ''));
        if (!Purok::isKnown($purok)) {
            flash('error', 'Choose a valid purok.');
            redirect('/admin/puroks');
        }

        $ids = (array) ($_POST['user_ids'] ?? []);
        $n   = Purok::assign($ids, $purok);
        if ($n === 0) {
            flash('error', 'Tick at least one resident to add.');
            redirect('/admin/puroks?purok=' . rawurlencode($purok));
        }

        AuditLog::record((int) $_SESSION['user_id'], 'purok.assigned', "{$n} resident(s) added to {$purok} (IDs " . implode(', ', array_map('intval', $ids)) . ')');
        flash('success', "{$n} resident(s) added to {$purok}.");
        redirect('/admin/puroks?purok=' . rawurlencode($purok));
    }

    /**
     * POST /admin/puroks/remove
     * Take one resident out of their purok (the account itself stays).
     */
    public function remove(): void
    {
        check_csrf();

        $id    = (int) ($_POST['user_id'] ?? 0);
        $purok = trim((string) ($_POST['purok'] ?? ''));
        $user  = $id > 0 ? User::find($id) : null;

        if ($user === null || ($user['role'] ?? '') !== 'resident') {
            flash('error', 'Resident not found.');
            redirect('/admin/puroks');
        }

        Purok::assign([$id], '');
        AuditLog::record((int) $_SESSION['user_id'], 'purok.removed', "{$user['full_name']} (ID #{$id}) removed from {$purok}");
        flash('success', "{$user['full_name']} was removed from {$purok}. The account was not deleted.");
        redirect('/admin/puroks?purok=' . rawurlencode($purok));
    }

    /**
     * POST /admin/puroks/residents
     * Register a resident in person, straight into a purok (verified).
     */
    public function storeResident(): void
    {
        check_csrf();

        $name    = trim((string) ($_POST['full_name'] ?? ''));
        $purok   = trim((string) ($_POST['purok'] ?? ''));
        $phone   = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $email   = trim((string) ($_POST['email'] ?? ''));
        $back    = '/admin/puroks?purok=' . rawurlencode($purok);

        $errors = [];
        if ($name === '' || mb_strlen($name) > 150) {
            $errors[] = 'Enter the resident\'s full name (up to 150 characters).';
        }
        if (!Purok::isKnown($purok)) {
            $errors[] = 'Choose a valid purok.';
            $back     = '/admin/puroks';
        }
        if ($phone !== '' && preg_match('/^(\+?63|0)?9\d{9}$/', preg_replace('/[\s\-()]/', '', $phone) ?? '') !== 1) {
            $errors[] = 'Enter a Philippine mobile number like 09171234567, or leave it blank.';
        }
        if ($email !== '') {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || Purok::isPlaceholderEmail($email)) {
                $errors[] = 'Enter a valid email address, or leave it blank.';
            } elseif (User::findByEmail(strtolower($email)) !== null) {
                $errors[] = 'That email already has an account. Add that resident from the list instead.';
            }
        }
        if (mb_strlen($address) > 500) {
            $errors[] = 'The address is too long.';
        }

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            redirect($back);
        }

        $created = Purok::createResident([
            'full_name' => $name,
            'purok'     => $purok,
            'phone'     => preg_replace('/[\s\-()]/', '', $phone) ?? '',
            'address'   => $address,
            'email'     => $email,
        ]);

        AuditLog::record((int) $_SESSION['user_id'], 'resident.created_by_staff', "{$name} (ID #{$created['id']}) registered in {$purok}");

        if ($created['password'] !== null) {
            $_SESSION['purok_new_login'] = ['name' => $name, 'email' => $created['email'], 'password' => $created['password']];
        }
        flash('success', "{$name} is now a registered resident of {$purok}.");
        redirect($back);
    }
}
