# Prompt for Claude Code — split-screen login (brand panel + form, animated)

Copy everything inside the box and paste it into Claude Code, opened in
`C:\xampp\htdocs\BarangGabay`. Attach the reference screenshot too if you can.

---

```
You are working on BarangGabay, my BSIT capstone: a PHP 8.2 / MySQL MVC web app
(XAMPP, no framework) in C:\xampp\htdocs\BarangGabay. Read CLAUDE.md first,
then read app/views/auth/login.php before changing anything.

## What I want
Redesign the login page into a SPLIT-SCREEN card, like the reference layout
I'm describing below. Right now it is one centered white card, 440px wide.

New layout — one rounded card, centered, about 900-1000px wide, two columns:

  LEFT PANEL (coloured, about 45% width)
    - our barangay seal / system logo at the top
    - "REPUBLIC OF THE PHILIPPINES" eyebrow text, small, letter-spaced
    - the system name, large and bold
    - underneath it, the barangay and municipality
    - a thin divider, then 3 short feature rows, each with a small icon tile:
        1. Announcements, events and ordinances in one place
        2. Manobo, Bisaya and Filipino translation
        3. Secure account with verified residents only
    - a small footer line at the bottom of the card

  RIGHT PANEL (white, about 55% width)
    - "Sign in" heading and one line of helper text
    - the error / success alert (keep the existing flash handling)
    - Email and Password fields with their icons, exactly the fields that are
      there now
    - the Sign In button, full width
    - "Forgot your password?" and the register link below it

On screens under 768px the two panels stack: brand panel on top (shorter,
logo + system name only), form below. Nothing may overflow horizontally.

## The movement I want
1. On page load, the two panels slide in toward each other and meet in the
   middle — left panel from the left, right panel from the right — then the
   card settles. Around 500-600ms, ease-out, no bouncing.
2. The form fields fade up in sequence after the card settles, staggered by
   about 60ms each.
3. When switching between the resident door and the staff door (the ?as=
   entry points already in this file), slide the RIGHT panel content out and
   the new one in, instead of a hard page jump feel.
4. Wrap ALL of this in @media (prefers-reduced-motion: reduce) so the
   animation is disabled for anyone who asked their device for less motion.
   The page must be fully usable with animation off.

## The background — read this part carefully
I want the page background to reflect the Manobo community our barangay
serves. Do NOT invent or download "tribal-looking" patterns and pass them off
as Manobo design — a made-up pattern on a government portal serving that
community is worse than a plain background.

So do it in two layers:
1. Ship a CSS-only abstract geometric background now: repeating diamonds and
   chevrons built with linear-gradient / conic-gradient, in an earth palette
   (deep indigo base, warm ochre, clay red, natural white), moving very slowly
   (a 40s+ drift, also disabled under prefers-reduced-motion). Keep it
   ABSTRACT and label it in a code comment as a placeholder motif, not as
   Manobo design.
2. Make the background swappable: read an optional image from
   public/images/auth-bg.jpg if that file exists, and fall back to the CSS
   pattern when it does not. Add a short note in the code comment saying the
   image should be a photo or textile provided by the community with
   permission, with credit recorded in the repo.
Tell me in your summary exactly which file I drop the photo into.

Whatever the background, the text on top of it must stay readable: minimum
4.5:1 contrast for body text. Use an overlay/scrim behind the card if needed.

## Do not copy the reference screenshot's branding
The layout pattern is what I want — the split card, the left brand panel, the
feature rows. Do NOT reproduce the seal, wordmark, institution name or colours
of the school in that screenshot. Use OUR logo via system_logo_url() with the
existing fallback icon, OUR system name via system_name(), and OUR location via
system_location().

## Keep every behaviour that is already there
- The POST target, the CSRF token field, and the whole form contract
- The entry doors (resident / staff) and $otherEntries links
- Flash success / error alerts, including $errorKind and $errorEntry
- The password show/hide toggle and its aria-label
- The language switch, if present, stays reachable
- Every user-facing string goes through t(). If you add new text (the eyebrow
  line, the 3 feature rows, the helper line), add the keys to BOTH lang/en.php
  and lang/fil.php in the login.* section — no hardcoded English in the view.
- Dark mode: the page must work in both themes, like the rest of the app.

## Then match register.php
Once I approve the login page, apply the same split-screen treatment to
app/views/auth/register.php so the two pages look like one system. Do not start
on it until I say the login page is right.

## Rules
- Bootstrap 5.3 + Bootstrap Icons + Inter are already loaded on this page —
  use them, do not add a new CSS framework or JS library.
- Keep the styles in the page's existing <style> block (this page is
  standalone, it does not use the main layout).
- No layout shift on load, no horizontal scrollbar at 360px width.
- Escape output with e(). Do not touch AuthController or any route.

## When you are done
1. php -l on every file you touched.
2. Show me screenshots or a description at 1440px, 768px and 360px width.
3. Confirm: animation off under prefers-reduced-motion, contrast checked, and
   login still works (right password, wrong password, pending account).
4. List any new t() keys you added.

Show me the login page first and wait for my feedback before doing register.
Explain what you did in simple English or Taglish.
```

---

## Notes for me

- The reference screenshot is another university's system. The split-card
  *layout* is a common pattern and fine to follow; their seal, name and blue
  brand are theirs, so the prompt tells Claude to use our own.
- The Manobo background is the part to be careful with. The prompt ships an
  abstract placeholder and leaves a slot at `public/images/auth-bg.jpg` for a
  real photo or woven textile — best asked from the barangay or the IP
  representative, with credit noted in the repo. That is also a stronger point
  for the defense than a generic pattern.
- Current login page: one centered card, max-width 440px, custom `<style>`
  block, Bootstrap 5.3 + Inter, with resident/staff entry doors already built
  in — so this is a restyle, not a rebuild.
