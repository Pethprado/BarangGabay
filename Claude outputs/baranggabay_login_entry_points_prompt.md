# Prompt for Claude — BarangGabay: role entry points on the login page

Paste this into Claude Code, working inside the project folder.

## What I want

On the login page, add links **below the sign-in form** that let someone go to the entry point for their role — Resident, Staff/Admin, and Super Admin — the way a school system has separate "Faculty" and "Student Portal" links. Plus an "About the System" link.

## Ground truth

- `app/views/auth/login.php` is a standalone page (its own `<head>`, not the main layout): white card on a blue gradient, Inter + Bootstrap 5 + Bootstrap Icons, Alpine for the password toggle. It already has the CSRF token, flash alerts, a suspended-account message, a `?from=pending` verification notice, a divider, and "Wala pang account? Mag-register dito".
- `routes/web.php` has exactly one login: `GET /login` → `AuthController@showLogin`, `POST /login` → `AuthController@login`. Roles in `users.role` are `resident`, `staff`, `admin`, `superadmin`, and the `role:` middleware guards the admin/superadmin routes.
- The page's copy is currently hardcoded Tagalog while the rest of the app goes through `t()`. Pick one and be consistent — if you wire `t()`, add the keys for all three languages.

## The one rule that must not be broken

**Keep a single authentication endpoint.** All entry points submit to the same `POST /login`, with the same CSRF check, the same rate limiting, and the same 2FA challenge. The role comes from the database record after the password is verified — never from the URL, a query string, or a hidden form field.

Concretely: `GET /login?as=staff` may change the heading, helper text, icon and accent colour. It must not change what the server does with the credentials. If a resident signs in from the staff entry, they authenticate normally and land on the resident home — no error, and nothing in the response that reveals whether the account exists or what role it holds.

Separate login *forms* per role would triple the surface to protect and secure nothing, since anyone can visit any of those URLs anyway. This is presentation only.

## What to build

1. A small "entry point" row below the existing register link, in the style of the rest of the card — quiet text links, not big buttons competing with Sign In. Something like:
   - `Residente ka ba? Resident Login →`
   - `Staff o Admin? Staff Login →`
   - `About the System` with an info icon (link it to a real page or section — don't add a dead link)

2. `showLogin()` reads an optional entry parameter and passes presentation variables to the view: the heading ("Sign in to your resident account" / "Sign in to your staff account"), a matching icon, and which links to show. Whitelist the accepted values server-side and fall back to the default entry for anything unrecognised — don't echo the raw parameter into the page.

3. After a successful login, redirect by the **actual** role from the database: resident → resident home (or `/pending` when unverified), staff/admin → `/admin`, superadmin → their dashboard. Check whether `AuthController@login` already does this and keep it as the single source of truth rather than adding a second redirect path.

4. Keep every existing behaviour working on all entries: the suspended-account message, the `?from=pending` notice, `old('email')`, the password toggle, and the register link (register is for residents — so show it on the resident entry and hide it on the staff one).

## About the Super Admin link — read before you build it

I want a superadmin entry, but **do not put a visible "Super Admin" link on the public login page.** A public link labelled that way tells anyone scanning the site exactly which account is the highest-value target, and it buys nothing: the superadmin can already sign in from the normal form, because the role comes from their user record.

Build it this way instead: superadmin authenticates through the same `POST /login` like everyone else, and if you want a distinct entry screen for it, give it an unlinked URL (e.g. `/login?as=system`) that isn't advertised anywhere on the page. If I later decide I want it visible, that's a one-line change — but default to not advertising it.

Whatever you do, the superadmin entry must not weaken anything: same rate limiting, same lockout, same 2FA. If `superadmin` accounts don't currently require 2FA, say so in your summary — for the role that can read error logs, revoke sessions and download backups, that's worth turning on.

## Design and testing

Match the existing card exactly — same fonts, spacing, link colour, and the `auth-divider` treatment. At 390px wide the links must wrap cleanly and stay tappable, not overflow the card. Keep them keyboard-reachable with visible focus styles.

## Definition of done

From each entry point, sign in as each of the four roles and confirm: everyone lands where their real role belongs regardless of which entry they used; a resident signing in on the staff entry gets no error and no hint about roles; CSRF, rate limiting and 2FA behave identically on all entries; the pending and suspended notices still appear; and nothing in the page source lets a client influence the role. Then summarise what you added and whether superadmin 2FA is currently enforced.
