# Prompt for Claude Code — carry the login design into resident, staff and admin

Copy everything inside the box and paste it into Claude Code, opened in
`C:\xampp\htdocs\BarangGabay`. Attach a screenshot of the new login page too.

---

```
You are working on BarangGabay, my BSIT capstone: a PHP 8.2 / MySQL MVC web app
(XAMPP, no framework) in C:\xampp\htdocs\BarangGabay. Read CLAUDE.md first.

## Where we are
You just redesigned app/views/auth/login.php: a dark split card — brand panel on
the left (logo, REPUBLIKA NG PILIPINAS eyebrow, system name, barangay, three
feature rows with icon tiles), sign-in form on the right, on a dark indigo
background with a subtle diagonal geometric motif. I like it.

## What I want now
Make the rest of the system look like it belongs to that same design — the
resident pages, and the staff / admin panel. Right now they use an older look.

This is a RESTYLE, not a rebuild. Do it CSS-first: change the shared
stylesheets and the two layouts, and touch individual views only where the
markup genuinely blocks the new style. Do not rewrite working pages.

## Step 1 — one set of design tokens
The login page carries its colours, radii, shadows, motif and motion timings in
its own <style> block. Pull those into ONE shared place (a :root token block
both stylesheets use), so there is a single source of truth:
- surface colours (page background, panel, card, raised card) for light AND dark
- brand accent, plus the role accents already used for resident / staff / admin
- text colours: primary, secondary, muted — each one contrast-checked
- radii, border colours, the card shadow, the focus ring
- motion: the durations and easing used by the login animation
Then make login.php consume those tokens instead of its own copies. After this
step the login page must look EXACTLY as it does now — prove it before moving on.

## Step 2 — resident side
app/views/layouts/main.php + public/assets/css/main.css.
Apply the tokens to: the navbar, cards, buttons, badges, form fields, empty
states, the footer, and the resident dashboard tiles. Keep the existing
fade-up scroll reveal and the [data-countup] animated numbers — they already
work, just make sure the new colours do not break them.

## Step 3 — staff / admin side
app/views/layouts/admin.php + public/assets/css/admin.css.
Apply the same tokens to: the sidebar, the topbar, stat cards, data tables,
filter bars, forms, modals and the activity feed. The sidebar should read like
the login's left brand panel — same dark surface, same logo treatment.
Keep the small accent difference between staff and admin so a user can still
tell which panel they are in at a glance, but make it a token, not a one-off.

## Step 4 — the motif, used sparingly
The diagonal geometric motif from the login background becomes the system's
texture. Use it ONLY in these places, at low opacity:
- the admin sidebar header, behind the logo
- page headers / hero strips
- empty states and the auth pages
Never behind body text, never behind tables or form fields. Body text keeps a
minimum 4.5:1 contrast everywhere. If a motif drops contrast below that, the
motif loses.

## Step 5 — the community emblem: read this carefully
I asked for something like the Manobo tribe's own logo. Do NOT do this:
- do not invent a "Manobo emblem" and present it as one
- do not copy a tribal or IP organisation's emblem found online — those belong
  to specific communities and organisations, and using one implies an
  endorsement my capstone does not have

Do this instead:
1. Keep the abstract geometric motif as the visual language. In a code comment,
   label it clearly as an original abstract motif, NOT as Manobo design.
2. Leave a slot for the real thing: if public/images/community-emblem.svg (or
   .png) exists, render it in the login brand panel and the admin sidebar
   footer, beside a short credit line read from a text file I control
   (public/images/community-emblem-credit.txt). If the file is absent, render
   nothing — no placeholder emblem.
3. Write a short README note at public/images/README.md saying that this slot is
   for an emblem or textile provided by the community with permission, and that
   the credit line must name the source.
Tell me in your summary exactly which files to drop in.

## Rules
- No new CSS framework and no new JS library. The resident side and the admin
  side each already have their own setup — keep each as it is.
- Both themes must work. Define every colour as a token in light, then override
  the tokens for dark. No colour may exist only inside a dark-mode block.
- Wrap animation in @media (prefers-reduced-motion: reduce).
- All user-facing text goes through t(); if you add any new label, add the key
  to BOTH lang/en.php and lang/fil.php.
- Bump the ?v= query string on main.css / admin.css / admin.js in the layouts
  whenever you change those files, or browsers keep serving the old copy.
- Do not change any route, controller, model or form contract. Markup edits
  must not touch name= attributes, CSRF fields, or Alpine bindings.
- No horizontal scrollbar at 360px, no layout shift on load.

## Order of work — stop for my review at each step
1. Step 1 tokens + proof the login is unchanged.
2. Resident dashboard and one list page (announcements).
3. The rest of the resident pages.
4. Admin dashboard and one list page.
5. The rest of the admin pages.
Show me each step before starting the next one.

## When you are done with a step
- php -l on every file you touched
- describe the result at 1440px, 768px and 360px, in light and dark
- list the contrast ratios for body text, muted text and buttons on the new
  surfaces
- list every file you changed and why

Explain what you did in simple English or Taglish.
```

---

## Notes for me

- **About the "Manobo logo".** A real tribal or IP organisation emblem belongs
  to that community, and putting one on my system without permission would
  imply they endorsed it. The prompt keeps an original abstract motif as the
  design language and leaves a file slot for a real emblem or textile if the
  barangay, the IPMR or the community gives me one, with a credit line. Asking
  the IPMR is also a stronger story for the defense than a downloaded graphic.
- The work is staged on purpose: tokens first, then resident, then admin, with
  a review at each step, so one bad colour does not get copied into 40 pages.
- Cache busting matters here — if the CSS looks unchanged after an edit, it is
  usually the old file still cached; the `?v=` bump is in the rules for that
  reason.
