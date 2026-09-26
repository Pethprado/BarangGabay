# Prompt for Claude — BarangGabay: "My Account" settings for staff/admin accounts

Paste this into Claude Code, working inside the project folder (`CLAUDE.md` is already loaded there as project instructions).

## Ground truth — read these before writing anything

- `routes/web.php` — residents have `/profile` (`GET`/`POST` → `ResidentController@profile` / `@updateProfile`, middleware `['auth','verified']`). **Back-office users have nothing equivalent.** A logged-in staff member has no page anywhere to manage their own account. `/admin/staff/create` only lets an admin/superadmin *create* staff; `/superadmin/settings` is system-wide settings, not per-user.
- Roles in this system are `resident`, `staff`, `admin`, `superadmin`, enforced by the `role:` middleware in `routes/web.php`.
- `users` table already has: `full_name`, `email`, `password_hash`, `phone`, `address`, `zone`, `role`, `status`, `avatar_url`, `email_verified`, plus the AI-ID columns. Use what's there before adding anything new.
- Two-factor auth already exists: `/two-factor`, `/two-factor/enable`, `/two-factor/disable`, `/two-factor/backup-codes`, all on plain `['auth']` middleware — so staff can already reach it, but nothing links to it from the admin UI.
- Email verification already exists (`/verify-email` → `AuthController@verifyEmail`, plus the `email_verified` column).
- `App\Models\AuditLog::record()` is used across the app; a sessions table exists (see `SuperAdminController@sessions` / `@revokeSession`); locale switching exists at `/set-locale/{locale}` but is session-only, not saved to the account.
- Look at `app/views/resident/profile.php` and `ResidentController::profile()/updateProfile()` first — the new page should follow that established pattern, but rendered in the admin layout (`app/views/layouts/admin.php`) with the admin styling, not the resident one.

## What to build

A **"My Account" settings page for back-office users**. Build it once for `role:admin,staff,superadmin` rather than staff-only — an admin needs to change their own password just as much as a staff member does, and two near-identical pages would drift apart. Suggested routes, matching the conventions already in `routes/web.php`:

```
['GET',  '/admin/account',          'AccountController@index',          ['auth', 'role:admin,staff,superadmin']],
['POST', '/admin/account/profile',  'AccountController@updateProfile',  ['auth', 'role:admin,staff,superadmin']],
['POST', '/admin/account/email',    'AccountController@updateEmail',    ['auth', 'role:admin,staff,superadmin']],
['POST', '/admin/account/password', 'AccountController@updatePassword', ['auth', 'role:admin,staff,superadmin']],
```

Add the link to the admin sidebar/topbar in `app/views/layouts/admin.php` (a "My Account" entry under the user's own name), or it will be a page nobody can find.

### Sections on the page

1. **Profile details** — full name, phone, avatar upload (reuse the existing `avatar_url` column and whatever upload validation the app already uses for ID photos/cover images: real MIME sniffing, size cap, not extension-trust), and a **designation** field: "Barangay Secretary", "Barangay Treasurer", "Kagawad", "SK Chairperson", "Barangay Health Worker", "Administrative Aide", etc. Add it as a new `users.designation` column — update **both** `schema.sql` and a new numbered file in `database/migrations/` (the established convention, see `runPendingMigrations()` in `config/database.php`). Surface that designation next to the staff member's name in the feedback chat threads so a resident can see they're talking to "Maria Santos — Barangay Secretary" rather than a generic label.

2. **Change email** — requires the current password to confirm. On change, set `email_verified = 0`, send the verification email through the existing `/verify-email` flow, and tell the user plainly that they must verify the new address. Reject an email already in use by another account.

3. **Change password** — requires current password; new password confirmed twice; enforce whatever minimum-strength rule registration already applies (don't invent a second, different rule); re-hash with `password_hash()`; regenerate the session; and invalidate this user's *other* sessions so a stolen session doesn't survive a password change.

4. **Security** — show 2FA status with enable/disable linking into the existing `/two-factor` pages (don't rebuild 2FA), and list the account's active sessions (device/IP/last-seen) with a "sign out other devices" action, reusing whatever the superadmin sessions module already does rather than writing a parallel implementation.

5. **Preferences** — notification preferences for the alerts that actually matter to barangay staff: new feedback message from a resident, new resident registration awaiting verification, new ordinance/announcement published. Also persist their preferred UI language (English / Filipino / Manobo) to the account instead of only to the session, so it survives logout. New columns here go in `schema.sql` + a migration, same as above.

6. **My recent activity** — the last ~20 `audit_logs` rows for this user's own `user_id`. Read-only. For a government system it's genuinely useful for a staff member to see their own action history.

## Security requirements — non-negotiable

- A user editing their own account must **never** be able to change their own `role` or `status`. Don't render those fields, and don't trust the POST body even if someone injects them — whitelist the updatable columns server-side. This is the obvious privilege-escalation hole in a page like this.
- Every route above: CSRF token validated (`check_csrf()`, as used throughout), prepared statements, and the user can only ever act on `$_SESSION['user_id']` — never accept a user ID from the request.
- Rate-limit the password and email change endpoints the way the login flow is rate-limited.
- `AuditLog::record()` on every successful change (profile updated, email changed, password changed, sessions revoked) — these are exactly the events an audit trail exists for.
- Keep the Tagalog-first bilingual copy style used across the rest of the admin views.

## Definition of done

Log in as an actual `staff` account (not admin) and walk the whole thing: update profile details and see them persist; change the email and confirm it flips to unverified and sends the verification mail; change the password, get logged out of other sessions, and log back in with the new one; toggle a notification preference and confirm it's honoured where those notifications are generated. Then confirm a staff user cannot escalate their own role by tampering with the form. Give me a short summary of what you built and flag anything you left incomplete instead of hiding it inside a "done" claim.
